import EnergyResourceActionService from './energy-resource-action';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export const UNLOAD_PROOF_FILE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];

/**
 * DESCARGA (end-of-day unload to inventory) actions.
 *
 * Unloads are created through `UnloadService` on the server (nested lines + side effects), so the
 * form works on a plain draft object (see `newDraft` / `prefill`) and `saveDraft` posts it.
 */
export default class UnloadActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-unload', {
            resourceRoute: 'unloads',
            componentPrefix: 'energy-unload',
            resourceKey: 'unload',
            modelNamePath: 'public_id',
            defaultAttributes: { status: 'draft', unit: 'gal', source: 'console' },
        });
    }

    /**
     * Empty draft for the form. `vehicle` / `loadPlan` are optional presets.
     */
    newDraft({ vehicle = null, loadPlan = null } = {}) {
        return {
            vehicle,
            vehicle_uuid: vehicle?.id ?? null,
            driver_uuid: null,
            load_plan_uuid: loadPlan?.id ?? null,
            load_plan_id: loadPlan?.public_id ?? null,
            manifest_uuid: null,
            inventory_place: null,
            inventory_place_uuid: null,
            inventory_tank_uuid: null,
            unit: 'gal',
            unloaded_at: new Date(),
            notes: '',
            lines: [],
            total_before_volume: 0,
            confirm: true,
            prefilled: false,
            unload: null,
            files: [],
        };
    }

    /**
     * Fill a draft from the vehicle's compartments (before = residual, unloaded = residual, remaining = 0).
     */
    @task *prefill(draft, vehicle) {
        if (!vehicle?.id) return draft;
        try {
            const { prefill } = yield this.getAction('unloads/prefill', { vehicle: vehicle.id });
            if (!prefill) return draft;

            draft.vehicle = vehicle;
            draft.vehicle_uuid = prefill.vehicle_uuid ?? vehicle.id;
            draft.driver_uuid = prefill.driver_uuid ?? null;
            if (!draft.load_plan_uuid) {
                draft.load_plan_uuid = prefill.load_plan_uuid ?? null;
                draft.load_plan_id = prefill.load_plan_id ?? null;
            }
            draft.manifest_uuid = prefill.manifest_uuid ?? null;
            draft.inventory_place_uuid = draft.inventory_place?.id ?? prefill.inventory_place_uuid ?? null;
            if (!draft.inventory_place && prefill.inventory_place_uuid) {
                draft.inventory_place =
                    this.store.peekRecord('place', prefill.inventory_place_uuid) ?? (yield this.store.findRecord('place', prefill.inventory_place_uuid).catch(() => null));
            }
            draft.inventory_tank_uuid = prefill.inventory_tank_uuid ?? null;
            draft.unit = prefill.unit ?? draft.unit;
            draft.unloaded_at = prefill.unloaded_at ? new Date(prefill.unloaded_at) : draft.unloaded_at;
            draft.lines = (prefill.lines ?? []).map((line) => ({ ...line }));
            draft.total_before_volume = prefill.total_before_volume ?? 0;
            draft.prefilled = true;
        } catch (error) {
            this.notifications.serverError(error);
        }
        return draft;
    }

    /**
     * Persist a draft. Returns the created `energy-unload` record.
     */
    @task *saveDraft(draft, { confirm = draft.confirm ?? true } = {}) {
        const body = {
            vehicle_uuid: draft.vehicle_uuid ?? draft.vehicle?.id,
            driver_uuid: draft.driver_uuid ?? null,
            load_plan_uuid: draft.load_plan_uuid ?? null,
            manifest_uuid: draft.manifest_uuid ?? null,
            inventory_place_uuid: draft.inventory_place?.id ?? draft.inventory_place_uuid ?? null,
            inventory_tank_uuid: draft.inventory_tank_uuid ?? null,
            unit: draft.unit ?? 'gal',
            source: 'console',
            unloaded_at: draft.unloaded_at instanceof Date ? draft.unloaded_at.toISOString() : draft.unloaded_at,
            notes: draft.notes ?? null,
            // proof files are uploaded against the draft first, so confirmation happens in a second call
            confirm: false,
            lines: (draft.lines ?? []).map((line) => ({
                compartment_uuid: line.compartment_uuid,
                product_uuid: line.product_uuid ?? null,
                unloaded_volume: Number(line.unloaded_volume) || 0,
                remaining_volume: Number(line.remaining_volume) || 0,
            })),
        };

        // a draft may already exist when proof files were attached before confirming
        const existing = draft.unload;
        const response = existing?.id
            ? yield this.fetch.put(`unloads/${existing.id}`, { unload: body }, { namespace: 'energyops/int/v1' })
            : yield this.postAction('unloads', { unload: body });
        this.store.pushPayload('energy-unload', response);
        const unload = this.store.peekRecord('energy-unload', response.unload.id);
        draft.unload = unload;

        if (confirm) {
            return yield this.confirmTask.perform(unload);
        }
        return unload;
    }

    /**
     * Make sure a persisted draft exists so proof files can be attached to it.
     */
    @task *ensureDraft(draft) {
        if (draft.unload?.id) return draft.unload;
        return yield this.saveDraft.perform(draft, { confirm: false });
    }

    @task *confirmTask(unload) {
        const response = yield this.postAction(`unloads/${unload.id}/confirm`);
        this.store.pushPayload('energy-unload', response);
        return this.store.peekRecord('energy-unload', unload.id);
    }

    /**
     * Open the "Registrar descarga" flow for a vehicle (from load plan / vehicle panel).
     */
    @action createForVehicle(vehicle, options = {}) {
        const draft = this.newDraft({ vehicle, loadPlan: options.loadPlan ?? null });
        return this.modalsManager.show('modals/resource', {
            title: this.intl.t('energyops.unload.register-title', { vehicle: vehicle?.displayName ?? vehicle?.display_name ?? vehicle?.name ?? '' }),
            acceptButtonText: this.intl.t('energyops.unload.confirm'),
            acceptButtonIcon: 'check',
            modalClass: 'modal-lg',
            component: 'energy-unload/form',
            resource: draft,
            confirm: async (modal) => {
                modal.startLoading();
                try {
                    const unload = await this.saveDraft.perform(modal.getOption('resource'), { confirm: true });
                    this.notifications.success(this.intl.t('energyops.unload.confirmed', { id: unload.public_id }));
                    modal.done();
                    if (typeof options.callback === 'function') options.callback(unload);
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            ...options,
        });
    }

    /**
     * Header action buttons for an unload panel.
     */
    actionButtonsFor(unload, options = {}) {
        const callback = options.callback ?? (() => unload.reload());
        return [
            {
                text: this.intl.t('energyops.unload.confirm'),
                type: 'primary',
                icon: 'check',
                size: 'xs',
                permission: 'energy-ops confirm energy-unload',
                visible: unload.isDraft,
                fn: () => this.confirm(unload, { callback }),
            },
            {
                icon: 'ellipsis-h',
                iconPrefix: 'fas',
                renderInPlace: true,
                items: [
                    {
                        text: this.intl.t('energyops.unload.void'),
                        icon: 'ban',
                        permission: 'energy-ops void energy-unload',
                        disabled: unload.isVoided,
                        class: 'text-red-500',
                        fn: () => this.void(unload, { callback }),
                    },
                    {
                        text: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.unload') }),
                        icon: 'trash',
                        permission: 'energy-ops delete energy-unload',
                        class: 'text-red-500',
                        fn: () => this.deleteAndReturn(unload, options.deleteOptions ?? {}),
                    },
                ],
            },
        ];
    }

    @action confirm(unload, options = {}) {
        return this.modalsManager.confirm({
            title: this.intl.t('energyops.unload.confirm-title', { id: unload.public_id }),
            body: this.intl.t('energyops.unload.confirm-prompt'),
            acceptButtonText: this.intl.t('energyops.unload.confirm'),
            acceptButtonIcon: 'check',
            confirm: async (modal) => {
                modal.startLoading();
                try {
                    await this.confirmTask.perform(unload);
                    this.notifications.success(this.intl.t('energyops.unload.confirmed', { id: unload.public_id }));
                    modal.done();
                    if (typeof options.callback === 'function') options.callback(unload);
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            ...options,
        });
    }

    @action void(unload, options = {}) {
        return this.modalsManager.confirm({
            title: this.intl.t('energyops.unload.void-title', { id: unload.public_id }),
            body: this.intl.t('energyops.unload.void-prompt'),
            acceptButtonText: this.intl.t('energyops.unload.void'),
            acceptButtonType: 'danger',
            acceptButtonIcon: 'ban',
            confirm: async (modal) => {
                modal.startLoading();
                try {
                    const response = await this.postAction(`unloads/${unload.id}/void`, { reason: modal.getOption('reason') });
                    this.store.pushPayload('energy-unload', response);
                    this.notifications.success(this.intl.t('energyops.unload.voided'));
                    modal.done();
                    if (typeof options.callback === 'function') options.callback(unload);
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            ...options,
        });
    }

    @task *forLoadPlan(loadPlan) {
        if (!loadPlan?.id) return [];
        try {
            const response = yield this.getAction(`unloads/for-load-plan/${loadPlan.id}`);
            this.store.pushPayload('energy-unload', response);
            return (response.unloads ?? []).map((row) => this.store.peekRecord('energy-unload', row.id)).filter(Boolean);
        } catch (error) {
            this.notifications.serverError(error);
            return [];
        }
    }

    @task *forVehicle(vehicle, limit = 20) {
        if (!vehicle?.id) return [];
        try {
            const response = yield this.getAction(`unloads/for-vehicle/${vehicle.id}`, { limit });
            this.store.pushPayload('energy-unload', response);
            return (response.unloads ?? []).map((row) => this.store.peekRecord('energy-unload', row.id)).filter(Boolean);
        } catch (error) {
            this.notifications.serverError(error);
            return [];
        }
    }
}
