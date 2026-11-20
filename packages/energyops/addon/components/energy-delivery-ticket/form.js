import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';
import { VOLUME_UNITS } from '../../utils/energy-options';

/**
 * Issue / edit a delivery ticket. When an order is chosen the active load plan's
 * stop draws for that order are offered so the ticket is tied to a compartment + product.
 */
export default class EnergyDeliveryTicketFormComponent extends Component {
    @service store;
    @service loadPlanActions;
    @service notifications;
    unitOptions = VOLUME_UNITS;

    @tracked draws = [];
    @tracked tanks = [];

    get resource() {
        return this.args.resource ?? this.args.ticket ?? this.args.model;
    }

    get orderLocked() {
        return this.args.lockOrder === true || this.args.overlay?.lockOrder === true;
    }

    get plannedFromDraw() {
        return this.resource.stop_draw?.planned_qty ?? null;
    }

    constructor() {
        super(...arguments);
        if (this.resource?.order) {
            this.loadDraws.perform(this.resource.order);
            this.loadTanks.perform(this.resource.order);
        }
    }

    @action setOrder(order) {
        this.resource.order = order;
        this.resource.order_uuid = order?.id ?? null;
        this.resource.stop_draw = null;
        this.resource.stop_draw_uuid = null;
        this.draws = [];
        if (order) {
            this.loadDraws.perform(order);
            this.loadTanks.perform(order);
        }
    }

    /** Native `<Select>` yields the option value (draw id); resolve it back to the record. */
    @action setDrawById(id) {
        this.setDraw(this.draws.find((draw) => draw.id === id) ?? null);
    }

    @action setDraw(draw) {
        this.resource.stop_draw = draw;
        this.resource.stop_draw_uuid = draw?.id ?? null;
        if (draw) {
            this.resource.compartment = draw.compartment;
            this.resource.compartment_uuid = draw.compartment_uuid ?? draw.compartment?.id ?? null;
            this.resource.product = draw.product;
            this.resource.product_uuid = draw.product_uuid ?? draw.product?.id ?? null;
            this.resource.load_plan_uuid = draw.load_plan_uuid ?? null;
            if (this.resource.planned_qty == null) this.resource.planned_qty = draw.planned_qty;
            if (!this.resource.metered_qty) this.resource.metered_qty = draw.planned_qty;
        }
    }

    @action setProduct(product) {
        this.resource.product = product;
        this.resource.product_uuid = product?.id ?? null;
        if (product?.default_unit && !this.resource.unit) this.resource.unit = product.default_unit;
    }

    @action setCompartment(compartment) {
        this.resource.compartment = compartment;
        this.resource.compartment_uuid = compartment?.id ?? null;
    }

    @action setTank(tank) {
        this.resource.customer_tank = tank;
        this.resource.customer_tank_uuid = tank?.id ?? null;
    }

    @task *loadDraws(order) {
        const plan = yield this.loadPlanActions.forOrder.perform(order);
        if (!plan) {
            this.draws = [];
            return;
        }
        this.resource.load_plan = plan;
        this.resource.load_plan_uuid = plan.id;
        this.draws = [...plan.draws].filter((draw) => (draw.order_uuid ?? draw.order?.id) === order.id).sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
        if (this.draws.length === 1 && !this.resource.stop_draw) {
            this.setDraw(this.draws[0]);
        }
    }

    @task *loadTanks(order) {
        const placeId = order?.payload?.dropoff?.id ?? order?.payload?.dropoff_uuid ?? null;
        if (!placeId) {
            this.tanks = [];
            return;
        }
        try {
            const tanks = yield this.store.query('energy-customer-tank', { place_uuid: placeId, limit: 50 });
            this.tanks = tanks.slice ? tanks.slice() : [...tanks];
            if (!this.resource.customer_tank) {
                // prefer the tank chosen on the order line, then the only tank for this product, then the only tank at the site
                const wanted = this.resource.customer_tank_uuid;
                const productId = this.resource.product_uuid ?? this.resource.product?.id;
                const forProduct = this.tanks.filter((tank) => (tank.product_uuid ?? tank.product?.id) === productId);
                const pick =
                    (wanted && this.tanks.find((tank) => tank.id === wanted)) || (forProduct.length === 1 ? forProduct[0] : null) || (this.tanks.length === 1 ? this.tanks[0] : null);
                if (pick) this.setTank(pick);
            }
        } catch {
            this.tanks = [];
        }
    }
}
