import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

/**
 * Modal body for "Agregar al camion cargado": pick late pedidos to draw from the product on board.
 * `@options.resource` is the POJO { loadPlan, orders: [], stock, totalAvailableLiters } mutated in place.
 */
export default class EnergyLoadPlanAddOrdersFormComponent extends Component {
    @service loadPlanActions;
    @tracked stock = [];
    @tracked totalAvailableLiters = null;

    get selection() {
        return this.args.options?.resource ?? this.args.resource;
    }

    get loadPlan() {
        return this.selection?.loadPlan;
    }

    get requestedLiters() {
        return (this.selection?.orders ?? []).reduce((sum, order) => sum + this.orderLiters(order), 0);
    }

    get exceedsStock() {
        return this.totalAvailableLiters != null && this.requestedLiters > this.totalAvailableLiters + 0.001;
    }

    constructor() {
        super(...arguments);
        this.loadStock.perform();
    }

    @task *loadStock() {
        const result = yield this.loadPlanActions.onBoardStock.perform(this.loadPlan);
        this.stock = result?.stock ?? [];
        this.totalAvailableLiters = result?.total_available_liters ?? 0;
        this.selection.stock = this.stock;
        this.selection.totalAvailableLiters = this.totalAvailableLiters;
    }

    /** Best-effort demand in liters from the order's energy entities (server does the real allocation). */
    orderLiters(order) {
        const entities = order?.payload?.entities ?? [];
        return entities.reduce((sum, entity) => {
            const meta = entity?.meta && typeof entity.meta === 'object' ? entity.meta : {};
            const qty = Number(meta.volume ?? meta.qty ?? 0);
            if (!Number.isFinite(qty) || qty <= 0) return sum;
            const unit = String(meta.unit ?? 'L').toLowerCase();
            if (unit === 'gal') return sum + qty * 3.785411784;
            if (unit === 'm3') return sum + qty * 1000;
            return sum + qty;
        }, 0);
    }

    @action addOrder(order) {
        if (!order || this.selection.orders.some((o) => o.id === order.id)) return;
        this.selection.orders = [...this.selection.orders, order];
    }

    @action removeOrder(order) {
        this.selection.orders = this.selection.orders.filter((o) => o !== order);
    }
}
