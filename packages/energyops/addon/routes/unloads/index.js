import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class UnloadsIndexRoute extends Route {
    @service store;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
        status: { refreshModel: true },
        vehicle: { refreshModel: true },
        inventory_place: { refreshModel: true },
        load_plan: { refreshModel: true },
        unloaded_at: { refreshModel: true },
        created_at: { refreshModel: true },
    };

    model(params) {
        return this.store.query('energy-unload', params);
    }
}
