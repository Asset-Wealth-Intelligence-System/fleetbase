import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';

export default class LoadPlansIndexDetailsController extends Controller {
    @service loadPlanActions;
    @service hostRouter;
    @service intl;
    @tracked overlay;

    get actionButtons() {
        return this.loadPlanActions.actionButtonsFor(this.model, { callback: () => this.model.reload() });
    }
}
