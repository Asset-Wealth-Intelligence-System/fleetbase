import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { task } from 'ember-concurrency';

/**
 * Key metrics tiles. Works standalone (dashboard widget: fetches its own data)
 * or embedded (Energy Ops home passes `@summary` / `@variance`).
 */
export default class EnergyWidgetKeyMetricsComponent extends Component {
    @service fetch;
    @service hostRouter;
    @service intl;

    @tracked fetchedSummary = {};
    @tracked fetchedVariance = {};

    get summary() {
        return this.args.summary ?? this.fetchedSummary ?? {};
    }

    get variance() {
        return this.args.variance ?? this.fetchedVariance ?? {};
    }

    get title() {
        return this.args.title ?? this.args.options?.title ?? this.intl.t('energyops.widget.title');
    }

    get tiles() {
        const s = this.summary;
        const v = this.variance;
        return [
            { label: this.intl.t('energyops.home.open-plans'), value: s.open_load_plans ?? 0, icon: 'clipboard-list', route: 'console.energy-ops.load-plans.index' },
            { label: this.intl.t('energyops.home.tickets-today'), value: v.tickets ?? s.tickets_30d ?? 0, icon: 'receipt', route: 'console.energy-ops.delivery-tickets.index' },
            { label: this.intl.t('energyops.home.products'), value: s.products ?? 0, icon: 'flask', route: 'console.energy-ops.products.index' },
            { label: this.intl.t('energyops.home.compartments'), value: s.compartments ?? 0, icon: 'truck-moving', route: 'console.energy-ops.fleet.index' },
            { label: this.intl.t('energyops.home.customer-tanks'), value: s.customer_tanks ?? 0, icon: 'oil-well', route: 'console.energy-ops.customer-tanks.index' },
            { label: this.intl.t('energyops.home.fleet-capacity'), value: s.fleet_capacity ?? 0, suffix: 'L', icon: 'gas-pump', route: 'console.energy-ops.fleet.index' },
            { label: this.intl.t('energyops.home.avg-variance'), value: v.avg_variance_percent ?? 0, suffix: '%', icon: 'chart-line', route: 'console.energy-ops.delivery-tickets.index' },
            {
                label: this.intl.t('energyops.home.exceptions'),
                value: v.exceptions ?? 0,
                icon: 'triangle-exclamation',
                route: 'console.energy-ops.delivery-tickets.index',
                danger: (v.exceptions ?? 0) > 0,
            },
        ];
    }

    constructor() {
        super(...arguments);
        if (!this.args.summary) {
            this.load.perform();
        }
    }

    @task *load() {
        try {
            const [summary, variance] = yield Promise.all([
                this.fetch.get('summary', {}, { namespace: 'energyops/int/v1' }),
                this.fetch.get('analytics/variance', { days: 30 }, { namespace: 'energyops/int/v1' }),
            ]);
            this.fetchedSummary = summary ?? {};
            this.fetchedVariance = variance ?? {};
        } catch {
            this.fetchedSummary = {};
            this.fetchedVariance = {};
        }
    }
}
