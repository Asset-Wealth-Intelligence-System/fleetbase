import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

const NAMESPACE = 'energyops/int/v1';

/**
 * Virtual settings page (/console/settings/energy-ops).
 * Company-level Energy Ops switches, safety constraints and order-config bootstrap.
 */
export default class EnergySettingsEnergyOpsSettingsComponent extends Component {
    @service fetch;
    @service notifications;
    @service intl;
    @service abilities;
    @service store;

    @tracked enabled = true;
    @tracked allocationEngine = 'vroom';
    @tracked autoAllocateOnCreate = false;
    @tracked surgeBand = false;
    @tracked axleBalance = false;
    @tracked requireLoadProof = true;
    @tracked requireUnloadProof = true;
    @tracked inventoryPlace = null;
    @tracked varianceThreshold = 0.15;
    @tracked summary = {};
    @tracked orderConfigs = [];

    get usesCompartmentEngine() {
        return this.allocationEngine === 'compartment';
    }

    get canManage() {
        return this.abilities.can('energy-ops manage settings') || this.abilities.can('energy-ops update settings');
    }

    constructor() {
        super(...arguments);
        this.load.perform();
    }

    @task *load() {
        try {
            const [settings, summary] = yield Promise.all([this.fetch.get('settings', {}, { namespace: NAMESPACE }), this.fetch.get('summary', {}, { namespace: NAMESPACE })]);
            this.enabled = settings.enabled ?? true;
            this.allocationEngine = settings.allocation_settings?.allocation_engine ?? 'vroom';
            this.autoAllocateOnCreate = settings.allocation_settings?.auto_allocate_on_create ?? false;
            this.surgeBand = settings.safety?.surge_band ?? false;
            this.axleBalance = settings.safety?.axle_balance ?? false;
            this.requireLoadProof = settings.safety?.require_load_proof ?? true;
            this.requireUnloadProof = settings.safety?.require_unload_proof ?? true;
            this.inventoryPlace = settings.inventory_place?.uuid ? yield this.resolvePlace(settings.inventory_place.uuid) : null;
            this.varianceThreshold = settings.variance_repack_threshold ?? 0.15;
            this.orderConfigs = settings.order_configs ?? [];
            this.summary = summary ?? {};
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *save() {
        try {
            const settings = yield this.fetch.post(
                'settings',
                {
                    enabled: this.enabled,
                    allocation_engine: this.allocationEngine,
                    auto_allocate_on_create: this.autoAllocateOnCreate,
                    safety: { surge_band: this.surgeBand, axle_balance: this.axleBalance, require_load_proof: this.requireLoadProof, require_unload_proof: this.requireUnloadProof },
                    inventory_place_uuid: this.inventoryPlace?.id ?? null,
                    variance_repack_threshold: Number(this.varianceThreshold),
                },
                { namespace: NAMESPACE }
            );
            this.orderConfigs = settings?.order_configs ?? this.orderConfigs;
            this.notifications.success(this.intl.t('energyops.settings.saved'));
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @task *installOrderConfigs() {
        try {
            const response = yield this.fetch.post('settings/install-order-configs', {}, { namespace: NAMESPACE });
            this.orderConfigs = response?.order_configs ?? [];
            this.notifications.success(this.intl.t('energyops.settings.configs-created'));
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    async resolvePlace(id) {
        return this.store.peekRecord('place', id) ?? (await this.store.findRecord('place', id).catch(() => null));
    }

    @action setInventoryPlace(place) {
        this.inventoryPlace = place ?? null;
    }

    @action toggleEngine(enabled) {
        this.allocationEngine = enabled ? 'compartment' : 'vroom';
    }
}
