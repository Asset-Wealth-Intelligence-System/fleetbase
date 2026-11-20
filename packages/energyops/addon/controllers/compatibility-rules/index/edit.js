import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class CompatibilityRulesIndexEditController extends Controller {
    @service compatibilityRuleActions;
    @service hostRouter;
    @tracked overlay;

    get actionButtons() {
        return [{ icon: 'eye', fn: this.view }];
    }

    @task *save(rule) {
        try {
            yield this.compatibilityRuleActions.saveTask.perform(rule);
        } catch {
            return;
        }
        this.overlay?.close();
        yield this.hostRouter.transitionTo('console.energy-ops.compatibility-rules.index.details', rule);
    }

    @action cancel() {
        if (this.model.hasDirtyAttributes) {
            return this.compatibilityRuleActions.confirmContinueWithUnsavedChanges(this.model, { redirectTo: 'console.energy-ops.compatibility-rules.index.details' });
        }
        return this.hostRouter.transitionTo('console.energy-ops.compatibility-rules.index');
    }

    @action view() {
        if (this.model.hasDirtyAttributes) {
            return this.compatibilityRuleActions.confirmContinueWithUnsavedChanges(this.model, { redirectTo: 'console.energy-ops.compatibility-rules.index.details' });
        }
        return this.hostRouter.transitionTo('console.energy-ops.compatibility-rules.index.details', this.model);
    }
}
