import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class OperationsIndexNewController extends Controller {
    @service operationActions;
    @service hostRouter;
    @tracked overlay;
    @tracked operation = this.operationActions.createNewInstance();

    @task *save(operation) {
        try {
            yield this.operationActions.saveTask.perform(operation);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.refresh();
        yield this.hostRouter.transitionTo('console.energy-ops.operations.index.details', operation);
        this.resetForm();
    }

    @action resetForm() {
        this.operation = this.operationActions.createNewInstance();
    }
}
