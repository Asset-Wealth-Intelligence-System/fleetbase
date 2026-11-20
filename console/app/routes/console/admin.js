import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ConsoleAdminRoute extends Route {
    @service intl;
    @service currentUser;
    @service notifications;
    @service router;

    beforeModel() {
        if (!this.currentUser.isAdmin) {
            return this.router.transitionTo('console').then(() => {
                this.notifications.error(this.intl.t('literal.console.you-do-not-have-authorization-to-access-admin'));
            });
        }
    }
}
