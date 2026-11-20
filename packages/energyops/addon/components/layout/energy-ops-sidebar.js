import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class LayoutEnergyOpsSidebarComponent extends Component {
    @service intl;
    @service abilities;
    @service hostRouter;
    @service loadPlanActions;

    routePrefix = 'console.energy-ops.';

    get sections() {
        return [
            {
                id: 'catalog',
                title: this.intl.t('energyops.nav.catalog'),
                icon: 'book',
                items: [
                    { route: 'operations', icon: 'industry', label: this.intl.t('energyops.nav.operations'), permission: 'energy-ops view energy-operation' },
                    { route: 'products', icon: 'flask', label: this.intl.t('energyops.nav.products'), permission: 'energy-ops view energy-product' },
                    { route: 'compatibility-rules', icon: 'shuffle', label: this.intl.t('energyops.nav.compatibility'), permission: 'energy-ops view energy-compatibility-rule' },
                ],
            },
            {
                id: 'fleet',
                title: this.intl.t('energyops.nav.fleet'),
                icon: 'truck-moving',
                items: [
                    { route: 'fleet', icon: 'layer-group', label: this.intl.t('energyops.nav.tank-configuration'), permission: 'energy-ops view energy-compartment' },
                    { route: 'customer-tanks', icon: 'oil-can', label: this.intl.t('energyops.nav.customer-tanks'), permission: 'energy-ops view energy-customer-tank' },
                ],
            },
            {
                id: 'dispatch',
                title: this.intl.t('energyops.nav.dispatch'),
                icon: 'route',
                items: [
                    { route: 'load-plans', icon: 'clipboard-list', label: this.intl.t('energyops.nav.load-plans'), permission: 'energy-ops view energy-load-plan' },
                    { route: 'delivery-tickets', icon: 'receipt', label: this.intl.t('energyops.nav.delivery-tickets'), permission: 'energy-ops view energy-delivery-ticket' },
                    { route: 'unloads', icon: 'arrow-down', label: this.intl.t('energyops.nav.unloads'), permission: 'energy-ops view energy-unload' },
                ],
            },
        ];
    }

    @action openSettings() {
        return this.hostRouter.transitionTo('console.settings.virtual', 'energy-ops');
    }
}
