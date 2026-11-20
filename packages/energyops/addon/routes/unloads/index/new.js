import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class UnloadsIndexNewRoute extends Route {
    @service notifications;
    @service hostRouter;
    @service abilities;
    @service intl;

    beforeModel() {
        if (this.abilities.cannot('energy-ops create energy-unload')) {
            this.notifications.warning(this.intl.t('common.unauthorized-access'));
            return this.hostRouter.transitionTo('console.energy-ops.unloads.index');
        }
    }
}
