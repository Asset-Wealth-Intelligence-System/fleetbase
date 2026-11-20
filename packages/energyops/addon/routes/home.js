import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class HomeRoute extends Route {
    @service fetch;
    @service store;

    async model() {
        const [summary, variance, recentPlans] = await Promise.all([
            this.fetch.get('summary', {}, { namespace: 'energyops/int/v1' }).catch(() => ({})),
            this.fetch.get('analytics/variance', { days: 30 }, { namespace: 'energyops/int/v1' }).catch(() => ({})),
            this.store.query('energy-load-plan', { sort: '-created_at', limit: 8 }).catch(() => []),
        ]);

        return { summary: summary ?? {}, variance: variance ?? {}, recentPlans };
    }
}
