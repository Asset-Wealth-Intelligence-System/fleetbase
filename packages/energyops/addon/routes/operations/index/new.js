import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class OperationsIndexNewRoute extends Route {
    @service notifications;
    @service hostRouter;
    @service abilities;
    @service intl;

    beforeModel() {
        if (this.abilities.cannot('energy-ops create energy-operation')) {
            this.notifications.warning(this.intl.t('common.unauthorized-access'));
            return this.hostRouter.transitionTo('console.energy-ops.operations.index');
        }
    }
}
