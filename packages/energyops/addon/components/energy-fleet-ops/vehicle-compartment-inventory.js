import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { getOwner } from '@ember/application';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';
import registerEnergyOps from '../../utils/register-energy-ops';

/**
 * Fleet-Ops slot `fleet-ops:component:vehicle:details`.
 * Read-only tanker schematic + compartment inventory with a shortcut to Tank configuration.
 */
export default class EnergyFleetOpsVehicleCompartmentInventoryComponent extends Component {
    @service vehicleProfileActions;
    @service compartmentActions;
    @service unloadActions;
    @service hostRouter;
    @service abilities;
    @service intl;
    @tracked profile;
    @tracked compartments = [];

    get vehicle() {
        return this.args.resource ?? this.args.vehicle;
    }

    get canSee() {
        return this.abilities.can('energy-ops see extension') && Boolean(this.vehicle?.id);
    }

    get compartmentOwner() {
        if (this.profile?.is_tractor && this.profile?.coupled_asset) {
            return this.profile.coupled_asset;
        }
        return this.vehicle;
    }

    constructor(owner) {
        super(...arguments);
        // Rendered inside the Fleet-Ops engine: expose our components/services to that owner
        registerEnergyOps(owner ?? getOwner(this));
        if (this.canSee) {
            this.load.perform();
        }
    }

    @task *load() {
        this.profile = yield this.vehicleProfileActions.loadForVehicle.perform(this.vehicle);
        this.compartments = yield this.compartmentActions.loadForOwner.perform(this.compartmentOwner);
    }

    get panelActionButtons() {
        return [
            { text: this.intl.t('energyops.unload.register'), icon: 'arrow-down', size: 'xs', onClick: this.registerUnload, permission: 'energy-ops create energy-unload' },
            { text: this.intl.t('energyops.fleet-ops.configure'), icon: 'sliders', size: 'xs', onClick: this.configure, permission: 'energy-ops update energy-compartment' },
        ];
    }

    @action configure() {
        this.hostRouter.transitionTo('console.energy-ops.fleet.index.details', this.vehicle);
    }

    @action registerUnload() {
        return this.unloadActions.createForVehicle(this.vehicle, { callback: () => this.load.perform() });
    }
}
