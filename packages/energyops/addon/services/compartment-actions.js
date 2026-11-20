import EnergyResourceActionService from './energy-resource-action';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';
import getModelName from '@fleetbase/ember-core/utils/get-model-name';

export default class CompartmentActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-compartment', {
            resourceRoute: 'fleet',
            componentPrefix: 'energy-compartment',
            defaultAttributes: { status: 'active', unit: 'L', max_fill_percent: 85, residual_volume: 0, requires_flush: false, capacity_volume: 0 },
        });
    }

    /**
     * Resolve the compartment owner type (`vehicle` | `asset`) for a Fleet-Ops record.
     */
    ownerTypeFor(owner) {
        const modelName = getModelName(owner);
        return modelName === 'asset' ? 'asset' : 'vehicle';
    }

    /**
     * Load compartments belonging to a vehicle or asset, ordered by sequence.
     */
    @task *loadForOwner(owner) {
        if (!owner?.id) return [];
        const compartments = yield this.store.query('energy-compartment', { owner_uuid: owner.id, owner_type: this.ownerTypeFor(owner), sort: 'sequence', limit: 100 });
        return compartments.slice ? compartments.slice() : [...compartments];
    }

    /**
     * Build a new unsaved compartment for an owner with the next sequence number.
     */
    buildForOwner(owner, existing = [], attributes = {}) {
        const sequence = existing.length + 1;
        return this.createNewInstance({
            owner_type: this.ownerTypeFor(owner),
            owner_uuid: owner.id,
            code: `C${sequence}`,
            name: `Compartment ${sequence}`,
            sequence,
            ...attributes,
        });
    }

    /**
     * Open a compartment form in a modal (used inside editors where a panel is already open).
     */
    @action modalEdit(compartment, options = {}) {
        const isNew = compartment.isNew;
        return this.modalsManager.show('modals/resource', {
            title: isNew ? this.intl.t('energyops.compartment.add') : this.intl.t('energyops.compartment.edit', { name: compartment.displayName }),
            acceptButtonText: this.intl.t('common.save'),
            acceptButtonIcon: 'save',
            component: 'energy-compartment/form',
            resource: compartment,
            modalClass: 'modal-lg',
            confirm: async (modal) => {
                modal.startLoading();
                try {
                    await compartment.save();
                    this.notifications.success(this.intl.t('energyops.compartment.saved', { name: compartment.displayName }));
                    if (typeof options.onSaved === 'function') {
                        options.onSaved(compartment);
                    }
                    modal.done();
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            decline: (modal) => {
                if (isNew) {
                    compartment.unloadRecord();
                } else {
                    compartment.rollbackAttributes();
                }
                modal.done();
            },
            ...options,
        });
    }

    /**
     * Persist a new order of compartments.
     */
    @task *reorder(compartments = []) {
        const order = compartments.map((compartment, index) => ({ uuid: compartment.id, sequence: index + 1 }));
        try {
            yield this.postAction('compartments/reorder', { compartments: order });
            compartments.forEach((compartment, index) => compartment.set('sequence', index + 1));
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    /**
     * Mark a compartment flushed (clears residual and last product).
     */
    @task *flush(compartment) {
        try {
            const response = yield this.postAction(`compartments/${compartment.id}/flush`);
            this.store.pushPayload('energy-compartment', response);
            this.notifications.success(this.intl.t('energyops.compartment.flushed', { name: compartment.displayName }));
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}
