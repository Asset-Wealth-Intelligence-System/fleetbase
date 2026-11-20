import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class LoadPlansIndexRoute extends Route {
    @service store;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
        status: { refreshModel: true },
        vehicle: { refreshModel: true },
        operation: { refreshModel: true },
        created_at: { refreshModel: true },
    };

    model(params) {
        return this.store.query('energy-load-plan', params);
    }
}
