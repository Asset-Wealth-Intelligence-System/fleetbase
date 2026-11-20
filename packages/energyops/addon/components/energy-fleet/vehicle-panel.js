import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action, set } from '@ember/object';
import { task } from 'ember-concurrency';

/**
 * Tabbed tanker configuration panel for a Fleet-Ops vehicle:
 * Profile (energy-vehicle-profile), Compartments (editor), Schematic, Load history.
 */
export default class FleetVehiclePanelComponent extends Component {
    @service vehicleProfileActions;
    @service compartmentActions;
    @service loadPlanActions;
    @service intl;
    @tracked profile;
    @tracked compartments = [];
    @tracked loadPlans = [];
    @tracked activeTab;

    get vehicle() {
        return this.args.vehicle ?? this.args.resource ?? this.args.model;
    }

    get tabs() {
        return (
            this.args.tabs ?? [
                { id: 'profile', label: this.intl.t('energyops.fleet.tabs.profile'), icon: 'id-card' },
                { id: 'compartments', label: this.intl.t('energyops.fleet.tabs.compartments'), icon: 'layer-group' },
                { id: 'schematic', label: this.intl.t('energyops.fleet.tabs.schematic'), icon: 'truck-moving' },
                { id: 'load-history', label: this.intl.t('energyops.fleet.tabs.load-history'), icon: 'clock-rotate-left' },
            ]
        );
    }

    get currentTab() {
        return this.args.activeTab ?? this.activeTab ?? 'profile';
    }

    /** Compartments live on the coupled trailer when the vehicle is a tractor. */
    get compartmentOwner() {
        if (this.profile?.is_tractor && this.profile?.coupled_asset) {
            return this.profile.coupled_asset;
        }
        return this.vehicle;
    }

    constructor() {
        super(...arguments);
        this.load.perform();
    }

    @task *load() {
        this.profile = yield this.vehicleProfileActions.loadForVehicle.perform(this.vehicle);
        yield this.loadCompartments.perform();
        this.loadPlans = yield this.loadPlanActions.forVehicle.perform(this.vehicle);
    }

    @task *loadCompartments() {
        this.compartments = yield this.compartmentActions.loadForOwner.perform(this.compartmentOwner);
        this.syncSummary();
    }

    /**
     * Keep the Fleet page row (`vehicle.energy_summary`, set by the fleet index route) in sync
     * with what was just edited in the panel so the list reflects changes without a full refresh.
     */
    syncSummary() {
        const vehicle = this.vehicle;
        if (!vehicle) {
            return;
        }

        const active = (this.compartments ?? []).filter((compartment) => !compartment.isDeleted);
        const profile = this.profile?.id ? this.profile : null;
        const operation = profile?.operation;

        set(vehicle, 'energy_summary', {
            ...(vehicle.energy_summary ?? {}),
            profile_uuid: profile?.id ?? null,
            profile_status: profile ? (active.length ? 'configured' : 'no_compartments') : 'no_profile',
            operation_uuid: profile?.operation_uuid ?? null,
            operation_name: operation?.get?.('name') ?? operation?.name ?? null,
            operation_code: operation?.get?.('code') ?? operation?.code ?? null,
            is_tractor: Boolean(profile?.is_tractor),
            coupled_asset_uuid: profile?.coupled_asset_uuid ?? null,
            compartments_count: active.length,
            total_capacity: active.reduce((sum, compartment) => sum + (Number(compartment.capacity_volume) || 0), 0),
            residual_volume: active.reduce((sum, compartment) => sum + (Number(compartment.residual_volume) || 0), 0),
            needs_flush: active.filter((compartment) => compartment.requires_flush).length,
        });
    }

    @action onTabChange(tab) {
        this.activeTab = tab?.id ?? tab;
        if (typeof this.args.onTabChange === 'function') {
            this.args.onTabChange(tab);
        }
    }

    @action onProfileSaved() {
        return this.loadCompartments.perform();
    }

    @action onCompartmentsChanged(compartments) {
        this.compartments = compartments;
        this.syncSummary();
    }
}
