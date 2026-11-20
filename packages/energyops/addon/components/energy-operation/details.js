import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { task } from 'ember-concurrency';

export default class OperationDetailsComponent extends Component {
    @service store;
    @service productActions;
    @tracked products = [];

    get resource() {
        return this.args.resource ?? this.args.operation ?? this.args.model;
    }

    constructor() {
        super(...arguments);
        this.loadProducts.perform();
    }

    @task *loadProducts() {
        if (!this.resource?.id) return;
        const products = yield this.store.query('energy-product', { operation_uuid: this.resource.id, limit: 100, sort: 'code' });
        this.products = products.slice ? products.slice() : [...products];
    }
}
