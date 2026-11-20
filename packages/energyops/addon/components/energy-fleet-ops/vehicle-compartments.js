import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { getOwner } from '@ember/application';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';
import registerEnergyOps from '../../utils/register-energy-ops';

/**
 * Fleet-Ops slot `fleet-ops:component:vehicle:form:after-details`.
 * Renders the energy profile form + compartment editor inside the vehicle form.
 */
export default class EnergyFleetOpsVehicleCompartmentsComponent extends Component {
    @service vehicleProfileActions;
    @service compartmentActions;
    @service abilities;
    @tracked profile;
    @tracked compartments = [];

    get vehicle() {
        return this.args.resource ?? this.args.vehicle;
    }

    get vehicleIsSaved() {
        return Boolean(this.vehicle?.id);
    }

    get canSee() {
        return this.abilities.can('energy-ops see extension');
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
        if (this.vehicleIsSaved && this.canSee) {
            this.load.perform();
        }
    }

    @task *load() {
        this.profile = yield this.vehicleProfileActions.loadForVehicle.perform(this.vehicle);
        this.compartments = yield this.compartmentActions.loadForOwner.perform(this.compartmentOwner);
    }

    @action onProfileSaved() {
        return this.load.perform();
    }

    @action onCompartmentsChanged(compartments) {
        this.compartments = compartments;
    }
}
