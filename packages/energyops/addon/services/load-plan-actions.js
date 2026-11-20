import EnergyResourceActionService from './energy-resource-action';
import { action } from '@ember/object';
import { getOwner } from '@ember/application';
import { task } from 'ember-concurrency';

export default class LoadPlanActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-load-plan', {
            resourceRoute: 'load-plans',
            componentPrefix: 'energy-load-plan',
            resourceKey: 'loadPlan',
            modelNamePath: 'public_id',
        });
    }

    /**
     * Header action buttons for a load plan panel (details route + context panel).
     */
    actionButtonsFor(loadPlan, options = {}) {
        const callback = options.callback ?? (() => loadPlan.reload());
        return [
            {
                text: this.intl.t('energyops.load-plan.confirm-load'),
                type: 'primary',
                icon: 'check',
                size: 'xs',
                permission: 'energy-ops confirm energy-load-plan',
                visible: loadPlan.canConfirmLoad,
                fn: () => this.confirmLoad(loadPlan, { callback }),
            },
            {
                text: this.intl.t('energyops.load-plan.add-orders'),
                type: 'default',
                icon: 'truck-ramp-box',
                size: 'xs',
                permission: 'energy-ops update energy-load-plan',
                visible: loadPlan.isOnBoard,
                fn: () => this.addOrders(loadPlan, { callback }),
            },
            {
                text: this.intl.t('energyops.unload.register'),
                type: 'default',
                icon: 'arrow-down',
                size: 'xs',
                permission: 'energy-ops create energy-unload',
                visible: loadPlan.isOnBoard,
                fn: () => this.registerUnload(loadPlan, { callback }),
            },
            {
                icon: 'ellipsis-h',
                iconPrefix: 'fas',
                renderInPlace: true,
                items: [
                    {
                        text: this.intl.t('energyops.load-plan.reload'),
                        icon: 'rotate',
                        permission: 'energy-ops reload energy-load-plan',
                        disabled: !loadPlan.isOpen,
                        fn: () => this.reload.perform(loadPlan, { callback }),
                    },
                    {
                        text: this.intl.t('energyops.load-plan.close'),
                        icon: 'lock',
                        permission: 'energy-ops update energy-load-plan',
                        disabled: !loadPlan.canClose,
                        fn: () => this.close.perform(loadPlan, { callback }),
                    },
                    { separator: true },
                    {
                        text: this.intl.t('energyops.load-plan.cancel'),
                        icon: 'ban',
                        permission: 'energy-ops update energy-load-plan',
                        disabled: !loadPlan.isOpen,
                        class: 'text-red-500',
                        fn: () => this.cancel.perform(loadPlan, { callback }),
                    },
                    {
                        text: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.load-plan') }),
                        icon: 'trash',
                        permission: 'energy-ops delete energy-load-plan',
                        class: 'text-red-500',
                        fn: () => this.deleteAndReturn(loadPlan, options.deleteOptions ?? {}),
                    },
                ],
            },
        ];
    }

    /**
     * Pack open EnergyOps orders onto compartments (runs the compartment engine).
     */
    @action packOrders(options = {}) {
        return this.modalsManager.show('modals/resource', {
            title: this.intl.t('energyops.load-plan.pack-orders'),
            acceptButtonText: this.intl.t('energyops.load-plan.pack'),
            acceptButtonIcon: 'boxes-packing',
            component: 'energy-load-plan/pack-form',
            resource: { order_ids: [], vehicle_ids: [], orders: [], vehicles: [], depot: null },
            confirm: async (modal) => {
                const selection = modal.getOption('resource');
                modal.startLoading();
                try {
                    const allocateOptions = {};
                    if (selection.depot?.id) {
                        allocateOptions.depot_place_uuid = selection.depot.id;
                    }
                    const result = await this.postAction('allocations/allocate', {
                        order_ids: (selection.orders ?? []).map((o) => o.public_id),
                        vehicle_ids: (selection.vehicles ?? []).map((v) => v.public_id),
                        options: allocateOptions,
                    });
                    const packed = result?.summary?.packed ?? 0;
                    const unassigned = result?.unassigned?.length ?? 0;
                    if (packed > 0) {
                        this.notifications.success(this.intl.t('energyops.load-plan.packed-success', { count: packed }));
                    } else {
                        this.notifications.warning(this.intl.t('energyops.load-plan.packed-none', { reason: result?.summary?.reason ?? 'no_feasible_vehicles' }));
                    }
                    if (unassigned > 0) {
                        this.notifications.info(this.intl.t('energyops.load-plan.unassigned', { count: unassigned }));
                    }
                    modal.done();
                    this.refresh();
                    if (typeof options.callback === 'function') options.callback(result);
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            ...options,
        });
    }

    /**
     * Confirm plant load: record loaded volumes per line and update residuals.
     */
    @action confirmLoad(loadPlan, options = {}) {
        const actuals = {};
        // Default each compartment to the planned volume unless an actual was already recorded
        loadPlan.lines.forEach((line) => {
            const loaded = Number(line.loaded_volume);
            actuals[line.id] = Number.isFinite(loaded) && loaded > 0 ? loaded : Number(line.planned_volume) || 0;
        });

        const existingProof = loadPlan.load_proof && typeof loadPlan.load_proof === 'object' ? loadPlan.load_proof : {};

        return this.modalsManager.show('modals/resource', {
            title: this.intl.t('energyops.load-plan.confirm-load-title', { id: loadPlan.public_id }),
            acceptButtonText: this.intl.t('energyops.load-plan.confirm-load'),
            acceptButtonIcon: 'check',
            modalClass: 'modal-lg',
            component: 'energy-load-plan/confirm-load-form',
            resource: {
                loadPlan,
                actuals,
                depot: loadPlan.depot ?? null,
                depot_place_uuid: loadPlan.depot_place_uuid ?? null,
                proof: { invoice_number: existingProof.invoice_number ?? '', ticket_number: existingProof.ticket_number ?? '', notes: existingProof.notes ?? '' },
                proofType: 'load_ticket',
                files: loadPlan.proofFiles,
            },
            confirm: async (modal) => {
                const data = modal.getOption('resource');
                modal.startLoading();
                try {
                    const response = await this.postAction(`load-plans/${loadPlan.id}/confirm-load`, {
                        actuals: data.actuals,
                        depot_place_uuid: data.depot_place_uuid ?? null,
                        proof: data.proof,
                    });
                    this.store.pushPayload('energy-load-plan', response);
                    this.notifications.success(this.intl.t('energyops.load-plan.confirm-success'));
                    modal.done();
                    if (typeof options.callback === 'function') options.callback(loadPlan);
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            ...options,
        });
    }

    /**
     * Add late pedidos to a loaded truck, drawing from the product on board (no reload trip).
     */
    @action addOrders(loadPlan, options = {}) {
        return this.modalsManager.show('modals/resource', {
            title: this.intl.t('energyops.load-plan.add-orders-title', { id: loadPlan.public_id }),
            acceptButtonText: this.intl.t('energyops.load-plan.add-orders'),
            acceptButtonIcon: 'truck-ramp-box',
            component: 'energy-load-plan/add-orders-form',
            resource: { loadPlan, orders: [], stock: [], totalAvailableLiters: null },
            confirm: async (modal) => {
                const selection = modal.getOption('resource');
                const orderIds = (selection.orders ?? []).map((o) => o.public_id);
                if (!orderIds.length) {
                    this.notifications.warning(this.intl.t('energyops.load-plan.add-orders-none-selected'));
                    return;
                }
                modal.startLoading();
                try {
                    const result = await this.postAction(`load-plans/${loadPlan.id}/add-orders`, { order_ids: orderIds });
                    if (result?.load_plan) {
                        this.store.pushPayload('energy-load-plan', { load_plan: result.load_plan });
                    }
                    const added = result?.added?.length ?? 0;
                    if (added > 0) {
                        this.notifications.success(this.intl.t('energyops.load-plan.add-orders-success', { count: added }));
                    }
                    (result?.skipped ?? []).forEach((skip) => {
                        this.notifications.warning(this.intl.t('energyops.load-plan.add-orders-skipped', { id: skip.order_id, reason: skip.reason }));
                    });
                    modal.done();
                    if (typeof options.callback === 'function') options.callback(result);
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            ...options,
        });
    }

    /**
     * Uncommitted product on board per compartment.
     */
    @task *onBoardStock(loadPlan) {
        if (!loadPlan?.id) return { stock: [], total_available_liters: 0 };
        try {
            return yield this.getAction(`load-plans/${loadPlan.id}/on-board-stock`);
        } catch (error) {
            this.notifications.serverError(error);
            return { stock: [], total_available_liters: 0 };
        }
    }

    /**
     * Register the end-of-day DESCARGA for this plan's vehicle.
     */
    @action registerUnload(loadPlan, options = {}) {
        const owner = getOwner(this);
        const unloadActions = owner.lookup('service:unload-actions');
        if (!unloadActions) return;
        return unloadActions.createForVehicle(loadPlan.vehicle, { loadPlan, ...options });
    }

    @task *close(loadPlan, options = {}) {
        try {
            const response = yield this.postAction(`load-plans/${loadPlan.id}/close`);
            this.store.pushPayload('energy-load-plan', response);
            this.notifications.success(this.intl.t('energyops.load-plan.closed'));
            if (typeof options.callback === 'function') options.callback(loadPlan);
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *cancel(loadPlan, options = {}) {
        try {
            const response = yield this.postAction(`load-plans/${loadPlan.id}/cancel`);
            this.store.pushPayload('energy-load-plan', response);
            this.notifications.success(this.intl.t('energyops.load-plan.canceled'));
            if (typeof options.callback === 'function') options.callback(loadPlan);
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *reload(loadPlan, options = {}) {
        try {
            const response = yield this.postAction(`load-plans/${loadPlan.id}/reload`);
            if (response?.ok === false) {
                this.notifications.warning(this.intl.t('energyops.load-plan.reload-none'));
            } else {
                this.notifications.success(this.intl.t('energyops.load-plan.reload-success'));
            }
            this.refresh();
            if (typeof options.callback === 'function') options.callback(response);
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    /**
     * Load the active load plan for a Fleet-Ops order.
     */
    @task *forOrder(order) {
        if (!order?.id) return null;
        try {
            const response = yield this.getAction(`load-plans/for-order/${order.id}`);
            if (!response?.load_plan) return null;
            this.store.pushPayload('energy-load-plan', response);
            return this.store.peekRecord('energy-load-plan', response.load_plan.uuid);
        } catch (error) {
            this.notifications.serverError(error);
            return null;
        }
    }

    /**
     * Load recent load plans for a vehicle.
     */
    @task *forVehicle(vehicle) {
        if (!vehicle?.id) return [];
        const plans = yield this.store.query('energy-load-plan', { vehicle_uuid: vehicle.id, sort: '-created_at', limit: 20 });
        return plans.slice ? plans.slice() : [...plans];
    }
}
