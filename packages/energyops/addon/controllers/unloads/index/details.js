import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';

export default class UnloadsIndexDetailsController extends Controller {
    @service unloadActions;
    @service hostRouter;
    @service intl;
    @tracked overlay;

    get actionButtons() {
        return this.unloadActions.actionButtonsFor(this.model, { callback: () => this.model.reload() });
    }
}
