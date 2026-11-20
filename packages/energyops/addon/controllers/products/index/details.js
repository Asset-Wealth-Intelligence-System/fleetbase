import Controller from '@ember/controller';
import { inject as service } from '@ember/service';

export default class ProductsIndexDetailsController extends Controller {
    @service productActions;
    @service hostRouter;
    @service intl;

    get actionButtons() {
        return [
            { icon: 'pencil', fn: () => this.hostRouter.transitionTo('console.energy-ops.products.index.edit', this.model), permission: 'energy-ops update energy-product' },
            {
                icon: 'ellipsis-h',
                iconPrefix: 'fas',
                renderInPlace: true,
                items: [
                    {
                        text: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.product') }),
                        icon: 'trash',
                        fn: () => this.productActions.deleteAndReturn(this.model),
                        permission: 'energy-ops delete energy-product',
                        class: 'text-red-500 hover:text-red-600',
                    },
                ],
            },
        ];
    }
}
