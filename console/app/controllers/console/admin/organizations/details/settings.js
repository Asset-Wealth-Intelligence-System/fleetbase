import Controller from '@ember/controller';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';

export default class ConsoleAdminOrganizationsDetailsSettingsController extends Controller {
    @service intl;
    @service router;
    @service fetch;
    @service notifications;
    @service modalsManager;

    @action editOrganization() {
        this.modalsManager.show('modals/edit-organization', {
            title: this.intl.t('literal.console.edit-organization'),
            acceptButtonText: this.intl.t('literal.console.save-changes'),
            acceptButtonIcon: 'save',
            organization: this.model,
            confirm: async (modal) => {
                modal.startLoading();

                try {
                    await this.model.save();
                    this.notifications.success(this.intl.t('literal.console.organization-updated'));
                    return this.router.refresh();
                } catch (error) {
                    this.notifications.serverError(error);
                }
            },
        });
    }

    @action setStatus(status) {
        this.modalsManager.confirm({
            title: this.intl.t('literal.console.update-organization-status'),
            body: this.intl.t('literal.console.set-this-organization-status-to', { status: status }),
            acceptButtonText: this.intl.t('literal.console.update-status'),
            confirm: async (modal) => {
                modal.startLoading();

                try {
                    await this.fetch.patch(`companies/${this.model.uuid}/status`, { status });
                    this.notifications.success(this.intl.t('literal.console.organization-status-updated'));
                    return this.router.refresh();
                } catch (error) {
                    this.notifications.serverError(error);
                }
            },
        });
    }

    @action setOnboarding(completed) {
        this.modalsManager.confirm({
            title: completed ? this.intl.t('literal.console.mark-onboarding-complete') : this.intl.t('literal.console.mark-onboarding-incomplete'),
            body: completed ? this.intl.t('literal.console.mark-this-organization-onboarding-as-complete') : this.intl.t('literal.console.mark-this-organization-onboarding-as-incomplete'),
            acceptButtonText: completed ? this.intl.t('literal.console.mark-complete') : this.intl.t('literal.console.mark-incomplete'),
            confirm: async (modal) => {
                modal.startLoading();

                try {
                    await this.fetch.patch(`companies/${this.model.uuid}/onboarding`, { completed });
                    this.notifications.success(this.intl.t('literal.console.organization-onboarding-updated'));
                    return this.router.refresh();
                } catch (error) {
                    this.notifications.serverError(error);
                }
            },
        });
    }
}
