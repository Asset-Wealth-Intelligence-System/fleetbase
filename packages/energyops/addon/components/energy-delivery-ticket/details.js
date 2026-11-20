import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class EnergyDeliveryTicketDetailsComponent extends Component {
    @service hostRouter;

    @action viewOrder(order) {
        if (!order) return;
        this.hostRouter.transitionTo('console.fleet-ops.operations.orders.index.details', order);
    }
}
