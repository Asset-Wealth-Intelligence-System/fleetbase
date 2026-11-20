import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class DeliveryTicketsIndexNewController extends Controller {
    @service deliveryTicketActions;
    @service hostRouter;
    @tracked overlay;
    @tracked ticket = this.deliveryTicketActions.createNewInstance();

    @task *save(ticket) {
        try {
            yield this.deliveryTicketActions.saveTask.perform(ticket);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.refresh();
        yield this.hostRouter.transitionTo('console.energy-ops.delivery-tickets.index.details', ticket);
        this.resetForm();
    }

    @action resetForm() {
        this.ticket = this.deliveryTicketActions.createNewInstance();
    }
}
