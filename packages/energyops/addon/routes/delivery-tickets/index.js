import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class DeliveryTicketsIndexRoute extends Route {
    @service store;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
        status: { refreshModel: true },
        product: { refreshModel: true },
        order_id: { refreshModel: true },
        load_plan: { refreshModel: true },
        exception_type: { refreshModel: true },
        created_at: { refreshModel: true },
    };

    model(params) {
        return this.store.query('energy-delivery-ticket', params);
    }
}
