import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class CustomerTanksIndexRoute extends Route {
    @service store;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
        status: { refreshModel: true },
        place: { refreshModel: true },
        product: { refreshModel: true },
        tank_type: { refreshModel: true },
        needs_reorder: { refreshModel: true },
        created_at: { refreshModel: true },
    };

    model(params) {
        return this.store.query('energy-customer-tank', params);
    }
}
