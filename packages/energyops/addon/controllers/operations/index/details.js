import Controller from '@ember/controller';
import { inject as service } from '@ember/service';

export default class OperationsIndexDetailsController extends Controller {
    @service operationActions;
    @service hostRouter;
    @service intl;

    get actionButtons() {
        return [
            { icon: 'pencil', fn: () => this.hostRouter.transitionTo('console.energy-ops.operations.index.edit', this.model), permission: 'energy-ops update energy-operation' },
            {
                icon: 'ellipsis-h',
                iconPrefix: 'fas',
                renderInPlace: true,
                items: [
                    {
                        text: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.operation') }),
                        icon: 'trash',
                        fn: () => this.operationActions.deleteAndReturn(this.model),
                        permission: 'energy-ops delete energy-operation',
                        class: 'text-red-500 hover:text-red-600',
                    },
                ],
            },
        ];
    }
}
