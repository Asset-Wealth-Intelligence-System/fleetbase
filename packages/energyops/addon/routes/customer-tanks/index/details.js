import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class CustomerTanksIndexDetailsRoute extends Route {
    @service store;
    @service notifications;
    @service hostRouter;
    @service abilities;
    @service intl;

    @action error(error) {
        this.notifications.serverError(error);
        return this.hostRouter.transitionTo('console.energy-ops.customer-tanks.index');
    }

    beforeModel() {
        if (this.abilities.cannot('energy-ops view energy-customer-tank')) {
            this.notifications.warning(this.intl.t('common.unauthorized-access'));
            return this.hostRouter.transitionTo('console.energy-ops.customer-tanks.index');
        }
    }

    model({ public_id }) {
        return this.store.findRecord('energy-customer-tank', public_id);
    }
}
