import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';

export const LOAD_PROOF_FILE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];

/**
 * Modal body for "Confirm plant load": actual volumes per line, loading station, proof numbers and
 * the comprobante de carga attachments (factura, boleta de carga, fotos).
 * `@options.resource` is the POJO { loadPlan, actuals, depot, proof, files } mutated in place.
 */
export default class EnergyLoadPlanConfirmLoadFormComponent extends Component {
    @service fetch;
    @service notifications;
    @service intl;
    @tracked uploadQueue = [];
    acceptedFileTypes = LOAD_PROOF_FILE_TYPES;

    get data() {
        return this.args.options?.resource ?? this.args.resource;
    }

    get loadPlan() {
        return this.data?.loadPlan;
    }

    get lines() {
        return [...(this.loadPlan?.lines ?? [])].sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    }

    get proof() {
        return this.data?.proof ?? {};
    }

    get files() {
        return this.data?.files ?? [];
    }

    get proofTypeOptions() {
        return [
            { value: 'load_invoice', label: this.intl.t('energyops.load-plan.proof.types.load_invoice') },
            { value: 'load_ticket', label: this.intl.t('energyops.load-plan.proof.types.load_ticket') },
            { value: 'load_photo', label: this.intl.t('energyops.load-plan.proof.types.load_photo') },
        ];
    }

    get proofType() {
        return this.data?.proofType ?? 'load_ticket';
    }

    @action setActual(line, event) {
        const value = parseFloat(event.target.value);
        this.data.actuals = { ...this.data.actuals, [line.id]: Number.isFinite(value) ? value : 0 };
    }

    @action setDepot(place) {
        this.data.depot = place ?? null;
        this.data.depot_place_uuid = place?.id ?? null;
    }

    @action setProofField(field, event) {
        this.data.proof = { ...this.proof, [field]: event.target.value };
    }

    @action setProofType(type) {
        this.data.proofType = type;
    }

    @task *queueFile(file) {
        if (['queued', 'failed', 'timed_out', 'aborted'].indexOf(file.state) === -1) return;
        if (!this.loadPlan?.id) return;

        try {
            this.uploadQueue.pushObject(file);
            yield this.fetch.uploadFile.perform(
                file,
                {
                    path: 'uploads/energyops/load-plans',
                    type: this.proofType,
                    subject_uuid: this.loadPlan.id,
                    subject_type: 'energyops:load-plan',
                },
                (uploadedFile) => {
                    this.data.files = [uploadedFile, ...this.files];
                    if (this.loadPlan.files && typeof this.loadPlan.files.pushObject === 'function') {
                        this.loadPlan.files.pushObject(uploadedFile);
                    }
                    this.uploadQueue.removeObject(file);
                },
                () => {
                    this.uploadQueue.removeObject(file);
                    if (file.queue && typeof file.queue.remove === 'function') {
                        file.queue.remove(file);
                    }
                }
            );
        } catch (err) {
            debug('Load proof upload failed: ' + err.message);
            this.notifications.serverError(err);
        }
    }

    @task *removeFile(file) {
        try {
            yield file.destroyRecord();
            this.data.files = this.files.filter((f) => f !== file);
        } catch (err) {
            this.notifications.serverError(err);
        }
    }
}
