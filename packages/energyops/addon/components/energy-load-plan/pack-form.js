import Component from '@glimmer/component';
import { action } from '@ember/object';

/**
 * Modal body for "Pack orders": pick Fleet-Ops orders (and optionally vehicles).
 * `@options.resource` is the POJO { orders: [], vehicles: [] } mutated in place.
 */
export default class EnergyLoadPlanPackFormComponent extends Component {
    get selection() {
        return this.args.options?.resource ?? this.args.resource;
    }

    @action addOrder(order) {
        if (!order || this.selection.orders.some((o) => o.id === order.id)) return;
        this.selection.orders = [...this.selection.orders, order];
    }

    @action removeOrder(order) {
        this.selection.orders = this.selection.orders.filter((o) => o !== order);
    }

    @action addVehicle(vehicle) {
        if (!vehicle || this.selection.vehicles.some((v) => v.id === vehicle.id)) return;
        this.selection.vehicles = [...this.selection.vehicles, vehicle];
    }

    @action removeVehicle(vehicle) {
        this.selection.vehicles = this.selection.vehicles.filter((v) => v !== vehicle);
    }

    /** Optional loading station for every plan packed in this run; defaults to each vehicle's habitual depot. */
    @action setDepot(place) {
        this.selection.depot = place ?? null;
    }
}
