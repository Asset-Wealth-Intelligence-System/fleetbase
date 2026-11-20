import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { getOwner } from '@ember/application';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';
import registerEnergyOps from '../../utils/register-energy-ops';

/**
 * Fleet-Ops slot `fleet-ops:component:place:form`.
 * Lists the customer tanks registered on the place and lets the user add / edit them.
 */
export default class EnergyFleetOpsPlaceCustomerTanksComponent extends Component {
    @service store;
    @service customerTankActions;
    @service abilities;
    @tracked tanks = [];

    get place() {
        return this.args.resource ?? this.args.place;
    }

    get placeIsSaved() {
        return Boolean(this.place?.id);
    }

    get canSee() {
        return this.abilities.can('energy-ops see extension');
    }

    constructor(owner) {
        super(...arguments);
        // Rendered inside the Fleet-Ops engine: expose our components/services to that owner
        registerEnergyOps(owner ?? getOwner(this));
        if (this.placeIsSaved && this.canSee) {
            this.load.perform();
        }
    }

    @task *load() {
        const tanks = yield this.store.query('energy-customer-tank', { place_uuid: this.place.id, limit: 100 });
        this.tanks = tanks.slice ? tanks.slice() : [...tanks];
    }

    @action addTank() {
        this.customerTankActions.createForPlace(this.place, { callback: () => this.load.perform(), lockPlace: true });
    }

    @action editTank(tank) {
        this.customerTankActions.panel.edit(tank, { callback: () => this.load.perform(), lockPlace: true });
    }

    @action viewTank(tank) {
        this.customerTankActions.panel.view(tank);
    }

    @action deleteTank(tank) {
        this.customerTankActions.delete(tank, { onConfirm: () => this.load.perform() });
    }
}
