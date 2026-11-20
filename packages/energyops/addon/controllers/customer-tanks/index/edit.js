import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class CustomerTanksIndexEditController extends Controller {
    @service customerTankActions;
    @service hostRouter;
    @tracked overlay;

    get actionButtons() {
        return [{ icon: 'eye', fn: this.view }];
    }

    @task *save(tank) {
        try {
            yield this.customerTankActions.saveTask.perform(tank);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.transitionTo('console.energy-ops.customer-tanks.index.details', tank);
    }

    @action cancel() {
        if (this.model.hasDirtyAttributes) {
            return this.customerTankActions.confirmContinueWithUnsavedChanges(this.model, { redirectTo: 'console.energy-ops.customer-tanks.index.details' });
        }
        return this.hostRouter.transitionTo('console.energy-ops.customer-tanks.index');
    }

    @action view() {
        if (this.model.hasDirtyAttributes) {
            return this.customerTankActions.confirmContinueWithUnsavedChanges(this.model, { redirectTo: 'console.energy-ops.customer-tanks.index.details' });
        }
        return this.hostRouter.transitionTo('console.energy-ops.customer-tanks.index.details', this.model);
    }
}
