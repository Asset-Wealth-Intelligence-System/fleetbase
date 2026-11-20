import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';
import { UNLOAD_PROOF_FILE_TYPES } from '../../services/unload-actions';

/**
 * DESCARGA form. Works on the plain draft object produced by `unloadActions.newDraft()`
 * (passed as `@resource`, or as `@options.resource` when rendered inside `modals/resource`).
 *
 * Proof files (photo / signature) are attached to a persisted draft, so the first upload creates it.
 */
export default class EnergyUnloadFormComponent extends Component {
    @service unloadActions;
    @service fetch;
    @service notifications;
    @service intl;
    @tracked uploadQueue = [];
    @tracked proofType = 'unload_photo';
    acceptedFileTypes = UNLOAD_PROOF_FILE_TYPES;

    /** Draft is a POJO: bump this tracked counter so getters re-render after in-place mutation. */
    @tracked revision = 0;

    get draft() {
        void this.revision;
        return this.args.options?.resource ?? this.args.resource ?? this.args.draft;
    }

    get lines() {
        void this.revision;
        return this.draft?.lines ?? [];
    }

    get files() {
        void this.revision;
        return this.draft?.files ?? [];
    }

    get totalUnloaded() {
        return this.lines.reduce((sum, line) => sum + (Number(line.unloaded_volume) || 0), 0);
    }

    get totalRemaining() {
        return this.lines.reduce((sum, line) => sum + (Number(line.remaining_volume) || 0), 0);
    }

    get proofTypeOptions() {
        return [
            { value: 'unload_photo', label: this.intl.t('energyops.proof-type.unload_photo') },
            { value: 'unload_signature', label: this.intl.t('energyops.proof-type.unload_signature') },
        ];
    }

    get unloadedAtValue() {
        const date = this.draft?.unloaded_at;
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    constructor() {
        super(...arguments);
        if (this.draft?.vehicle && !this.draft.prefilled) {
            this.unloadActions.prefill.perform(this.draft, this.draft.vehicle).then(() => this.touch());
        }
    }

    touch() {
        this.revision++;
        if (this.draft) {
            this.draft.lines = [...(this.draft.lines ?? [])];
        }
    }

    @action setVehicle(vehicle) {
        if (!this.draft) return;
        this.draft.vehicle = vehicle ?? null;
        this.draft.vehicle_uuid = vehicle?.id ?? null;
        this.draft.load_plan_uuid = null;
        this.draft.load_plan_id = null;
        this.draft.lines = [];
        this.draft.prefilled = false;
        if (vehicle) {
            this.unloadActions.prefill.perform(this.draft, vehicle).then(() => this.touch());
        } else {
            this.touch();
        }
    }

    @action setInventoryPlace(place) {
        this.draft.inventory_place = place ?? null;
        this.draft.inventory_place_uuid = place?.id ?? null;
        this.touch();
    }

    @action setUnloadedAt(event) {
        const value = event.target.value;
        this.draft.unloaded_at = value ? new Date(value) : null;
        this.touch();
    }

    @action setNotes(event) {
        this.draft.notes = event.target.value;
    }

    @action setLineVolume(line, field, event) {
        const value = parseFloat(event.target.value);
        line[field] = Number.isFinite(value) && value >= 0 ? value : 0;
        const before = Number(line.before_volume) || 0;
        if (field === 'unloaded_volume') {
            line.remaining_volume = Math.max(0, Math.round((before - line.unloaded_volume) * 1000) / 1000);
        } else if (field === 'remaining_volume') {
            line.unloaded_volume = Math.max(0, Math.round((before - line.remaining_volume) * 1000) / 1000);
        }
        this.touch();
    }

    @action setProofType(type) {
        this.proofType = type;
    }

    @task *queueFile(file) {
        if (['queued', 'failed', 'timed_out', 'aborted'].indexOf(file.state) === -1) return;
        if (!this.draft?.vehicle_uuid) {
            this.notifications.warning(this.intl.t('energyops.unload.select-vehicle-first'));
            return;
        }

        try {
            const unload = yield this.unloadActions.ensureDraft.perform(this.draft);
            this.uploadQueue.pushObject(file);
            yield this.fetch.uploadFile.perform(
                file,
                {
                    path: 'uploads/energyops/unloads',
                    type: this.proofType,
                    subject_uuid: unload.id,
                    subject_type: 'energyops:unload',
                },
                (uploadedFile) => {
                    this.draft.files = [uploadedFile, ...this.files];
                    if (unload.files && typeof unload.files.pushObject === 'function') {
                        unload.files.pushObject(uploadedFile);
                    }
                    this.uploadQueue.removeObject(file);
                    this.touch();
                },
                () => {
                    this.uploadQueue.removeObject(file);
                    if (file.queue && typeof file.queue.remove === 'function') {
                        file.queue.remove(file);
                    }
                }
            );
        } catch (err) {
            debug('Unload proof upload failed: ' + err.message);
            this.notifications.serverError(err);
        }
    }

    @task *removeFile(file) {
        try {
            yield file.destroyRecord();
            this.draft.files = this.files.filter((f) => f !== file);
            this.touch();
        } catch (err) {
            this.notifications.serverError(err);
        }
    }
}
