import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class ProductsIndexEditController extends Controller {
    @service productActions;
    @service hostRouter;
    @tracked overlay;

    get actionButtons() {
        return [{ icon: 'eye', fn: this.view }];
    }

    @task *save(product) {
        try {
            yield this.productActions.saveTask.perform(product);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.transitionTo('console.energy-ops.products.index.details', product);
    }

    @action cancel() {
        if (this.model.hasDirtyAttributes) {
            return this.productActions.confirmContinueWithUnsavedChanges(this.model, { redirectTo: 'console.energy-ops.products.index.details' });
        }
        return this.hostRouter.transitionTo('console.energy-ops.products.index');
    }

    @action view() {
        if (this.model.hasDirtyAttributes) {
            return this.productActions.confirmContinueWithUnsavedChanges(this.model, { redirectTo: 'console.energy-ops.products.index.details' });
        }
        return this.hostRouter.transitionTo('console.energy-ops.products.index.details', this.model);
    }
}
