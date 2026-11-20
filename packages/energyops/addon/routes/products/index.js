import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ProductsIndexRoute extends Route {
    @service store;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
        status: { refreshModel: true },
        operation: { refreshModel: true },
        hazmat_class: { refreshModel: true },
        created_at: { refreshModel: true },
    };

    model(params) {
        return this.store.query('energy-product', params);
    }
}
