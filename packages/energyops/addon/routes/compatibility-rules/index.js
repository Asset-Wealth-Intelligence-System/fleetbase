import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class CompatibilityRulesIndexRoute extends Route {
    @service store;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
        action: { refreshModel: true },
        from_product: { refreshModel: true },
        to_product: { refreshModel: true },
        status: { refreshModel: true },
    };

    model(params) {
        return this.store.query('energy-compatibility-rule', params);
    }
}
