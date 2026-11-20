import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class CustomerTanksIndexNewController extends Controller {
    @service customerTankActions;
    @service hostRouter;
    @tracked overlay;
    @tracked tank = this.customerTankActions.createNewInstance();

    @task *save(tank) {
        try {
            yield this.customerTankActions.saveTask.perform(tank);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.refresh();
        yield this.hostRouter.transitionTo('console.energy-ops.customer-tanks.index.details', tank);
        this.resetForm();
    }

    @action resetForm() {
        this.tank = this.customerTankActions.createNewInstance();
    }
}
