import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class ProductsIndexNewController extends Controller {
    @service productActions;
    @service hostRouter;
    @tracked overlay;
    @tracked product = this.productActions.createNewInstance();

    @task *save(product) {
        try {
            yield this.productActions.saveTask.perform(product);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.refresh();
        yield this.hostRouter.transitionTo('console.energy-ops.products.index.details', product);
        this.resetForm();
    }

    @action resetForm() {
        this.product = this.productActions.createNewInstance();
    }
}
