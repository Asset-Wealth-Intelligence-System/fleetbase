import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';

export default class DeliveryTicketsIndexDetailsController extends Controller {
    @service deliveryTicketActions;
    @service hostRouter;
    @service intl;
    @tracked overlay;

    get actionButtons() {
        return this.deliveryTicketActions.actionButtonsFor(this.model, { callback: () => this.model.reload() });
    }
}
