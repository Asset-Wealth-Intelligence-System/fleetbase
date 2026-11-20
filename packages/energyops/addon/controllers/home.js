import Controller from '@ember/controller';
import { inject as service } from '@ember/service';

export default class HomeController extends Controller {
    @service loadPlanActions;
    @service hostRouter;

    get summary() {
        return this.model?.summary ?? {};
    }

    get variance() {
        return this.model?.variance ?? {};
    }

    get byProduct() {
        return this.variance?.by_product ?? [];
    }

    get steps() {
        return [
            { key: 'step-operation', route: 'console.energy-ops.operations.index', done: (this.summary.operations ?? 0) > 0 },
            { key: 'step-products', route: 'console.energy-ops.products.index', done: (this.summary.products ?? 0) > 0 },
            { key: 'step-compartments', route: 'console.energy-ops.fleet.index', done: (this.summary.compartments ?? 0) > 0 },
            { key: 'step-tanks', route: 'console.energy-ops.customer-tanks.index', done: (this.summary.customer_tanks ?? 0) > 0 },
            { key: 'step-orders', route: 'console.energy-ops.load-plans.index', done: (this.summary.load_plans_total ?? 0) > 0 },
        ];
    }
}
