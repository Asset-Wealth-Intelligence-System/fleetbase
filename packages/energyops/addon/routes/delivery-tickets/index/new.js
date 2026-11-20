import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class DeliveryTicketsIndexNewRoute extends Route {
    @service notifications;
    @service hostRouter;
    @service abilities;
    @service intl;

    beforeModel() {
        if (this.abilities.cannot('energy-ops create energy-delivery-ticket')) {
            this.notifications.warning(this.intl.t('common.unauthorized-access'));
            return this.hostRouter.transitionTo('console.energy-ops.delivery-tickets.index');
        }
    }
}
