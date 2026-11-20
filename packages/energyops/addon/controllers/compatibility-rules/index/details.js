import Controller from '@ember/controller';
import { inject as service } from '@ember/service';

export default class CompatibilityRulesIndexDetailsController extends Controller {
    @service compatibilityRuleActions;
    @service hostRouter;
    @service intl;

    get actionButtons() {
        return [
            {
                icon: 'pencil',
                fn: () => this.hostRouter.transitionTo('console.energy-ops.compatibility-rules.index.edit', this.model),
                permission: 'energy-ops update energy-compatibility-rule',
            },
            {
                icon: 'ellipsis-h',
                iconPrefix: 'fas',
                renderInPlace: true,
                items: [
                    {
                        text: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.compatibility-rule') }),
                        icon: 'trash',
                        fn: () => this.compatibilityRuleActions.deleteAndReturn(this.model),
                        permission: 'energy-ops delete energy-compatibility-rule',
                        class: 'text-red-500 hover:text-red-600',
                    },
                ],
            },
        ];
    }
}
