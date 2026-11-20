import Controller from '@ember/controller';
import { inject as service } from '@ember/service';

export default class CustomerTanksIndexDetailsController extends Controller {
    @service customerTankActions;
    @service hostRouter;
    @service intl;

    get actionButtons() {
        return [
            { icon: 'pencil', fn: () => this.hostRouter.transitionTo('console.energy-ops.customer-tanks.index.edit', this.model), permission: 'energy-ops update energy-customer-tank' },
            {
                icon: 'ellipsis-h',
                iconPrefix: 'fas',
                renderInPlace: true,
                items: [
                    {
                        text: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.customer-tank') }),
                        icon: 'trash',
                        fn: () => this.customerTankActions.deleteAndReturn(this.model),
                        permission: 'energy-ops delete energy-customer-tank',
                        class: 'text-red-500 hover:text-red-600',
                    },
                ],
            },
        ];
    }
}
