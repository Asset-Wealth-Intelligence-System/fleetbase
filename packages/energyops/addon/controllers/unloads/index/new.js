import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class UnloadsIndexNewController extends Controller {
    @service unloadActions;
    @service notifications;
    @service intl;
    @service hostRouter;
    @tracked overlay;
    @tracked draft = this.unloadActions.newDraft();

    get actionButtons() {
        return [
            {
                text: this.intl.t('energyops.unload.confirm'),
                type: 'primary',
                icon: 'check',
                size: 'sm',
                permission: 'energy-ops create energy-unload',
                isLoading: this.save.isRunning,
                perform: this.save,
            },
        ];
    }

    @task *save() {
        let unload;
        try {
            unload = yield this.unloadActions.saveDraft.perform(this.draft, { confirm: true });
        } catch (error) {
            this.notifications.serverError(error);
            return;
        }
        this.notifications.success(this.intl.t('energyops.unload.confirmed', { id: unload.public_id }));
        this.overlay?.close();
        yield this.hostRouter.refresh();
        yield this.hostRouter.transitionTo('console.energy-ops.unloads.index.details', unload);
        this.resetForm();
    }

    @action resetForm() {
        this.draft = this.unloadActions.newDraft();
    }
}
