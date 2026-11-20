import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class OperationsIndexEditController extends Controller {
    @service operationActions;
    @service hostRouter;
    @tracked overlay;

    get actionButtons() {
        return [{ icon: 'eye', fn: this.view }];
    }

    @task *save(operation) {
        try {
            yield this.operationActions.saveTask.perform(operation);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.transitionTo('console.energy-ops.operations.index.details', operation);
    }

    @action cancel() {
        if (this.model.hasDirtyAttributes) {
            return this.operationActions.confirmContinueWithUnsavedChanges(this.model, { redirectTo: 'console.energy-ops.operations.index.details' });
        }
        return this.hostRouter.transitionTo('console.energy-ops.operations.index');
    }

    @action view() {
        if (this.model.hasDirtyAttributes) {
            return this.operationActions.confirmContinueWithUnsavedChanges(this.model, { redirectTo: 'console.energy-ops.operations.index.details' });
        }
        return this.hostRouter.transitionTo('console.energy-ops.operations.index.details', this.model);
    }
}
