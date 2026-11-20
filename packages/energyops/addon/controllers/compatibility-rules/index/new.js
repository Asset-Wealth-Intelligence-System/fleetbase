import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class CompatibilityRulesIndexNewController extends Controller {
    @service compatibilityRuleActions;
    @service hostRouter;
    @tracked overlay;
    @tracked rule = this.compatibilityRuleActions.createNewInstance();

    @task *save(rule) {
        try {
            yield this.compatibilityRuleActions.saveTask.perform(rule);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.refresh();
        yield this.hostRouter.transitionTo('console.energy-ops.compatibility-rules.index.details', rule);
        this.resetForm();
    }

    @action resetForm() {
        this.rule = this.compatibilityRuleActions.createNewInstance();
    }
}
