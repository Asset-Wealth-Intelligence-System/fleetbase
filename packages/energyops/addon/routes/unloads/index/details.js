import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class UnloadsIndexDetailsRoute extends Route {
    @service store;
    @service notifications;
    @service hostRouter;
    @service abilities;
    @service intl;

    @action error(error) {
        this.notifications.serverError(error);
        return this.hostRouter.transitionTo('console.energy-ops.unloads.index');
    }

    beforeModel() {
        if (this.abilities.cannot('energy-ops view energy-unload')) {
            this.notifications.warning(this.intl.t('common.unauthorized-access'));
            return this.hostRouter.transitionTo('console.energy-ops.unloads.index');
        }
    }

    model({ public_id }) {
        return this.store.findRecord('energy-unload', public_id);
    }
}
