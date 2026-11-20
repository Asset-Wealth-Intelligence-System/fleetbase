import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { later } from '@ember/runloop';
import window from 'ember-window-mock';

export default class ConsoleAdminOrganizationsDetailsUsersController extends Controller {
    @service intl;
    @service router;
    @service fetch;
    @service notifications;
    @service modalsManager;
    @service session;

    @tracked nestedPage = 1;
    @tracked nestedLimit = 20;
    @tracked nestedSort = '-created_at';
    @tracked nestedQuery = '';
    @tracked company;
    @tracked table;

    queryParams = ['nestedPage', 'nestedLimit', 'nestedSort', 'nestedQuery'];
    actionButtons = [];
    bulkActions = [];

    get sort() {
        return this.nestedSort;
    }

    set sort(value) {
        this.nestedSort = value;
    }

    get columns() {
        return [
            {
                label: this.intl.t('common.name'),
                valuePath: 'name',
                sticky: true,
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'nestedQuery',
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('common.role'),
                valuePath: 'roleName',
                resizable: true,
                sortable: true,
            },
            {
                label: this.intl.t('common.phone'),
                valuePath: 'phone',
                resizable: true,
            },
            {
                label: this.intl.t('common.email'),
                valuePath: 'email',
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'nestedQuery',
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('common.status'),
                valuePath: 'status',
                cellComponent: 'table/cell/status',
                resizable: true,
                sortable: true,
            },
            {
                label: '',
                cellComponent: 'table/cell/dropdown',
                ddButtonText: false,
                ddButtonIcon: 'ellipsis-h',
                ddButtonIconPrefix: 'fas',
                ddMenuLabel: this.intl.t('literal.console.user-actions'),
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                width: '9%',
                actions: [
                    {
                        label: this.intl.t('literal.console.impersonate'),
                        icon: 'user-secret',
                        fn: this.impersonateUser,
                    },
                    {
                        label: this.intl.t('literal.console.change-password'),
                        icon: 'lock-open',
                        fn: this.changeUserPassword,
                    },
                    {
                        separator: true,
                    },
                    {
                        label: this.intl.t('literal.console.verify'),
                        icon: 'circle-check',
                        fn: this.verifyUser,
                    },
                    {
                        label: this.intl.t('literal.console.activate'),
                        icon: 'user-check',
                        fn: this.activateUser,
                    },
                    {
                        label: this.intl.t('literal.console.deactivate'),
                        icon: 'user-slash',
                        fn: this.deactivateUser,
                    },
                    {
                        label: this.intl.t('literal.console.transfer-ownership'),
                        icon: 'crown',
                        fn: this.transferOwnership,
                    },
                    {
                        label: this.intl.t('literal.console.remove-from-organization'),
                        icon: 'user-xmark',
                        class: 'text-red-600',
                        fn: this.removeUser,
                    },
                ],
                sortable: false,
                filterable: false,
                resizable: false,
                searchable: false,
                sticky: 'right',
            },
        ];
    }

    @action async impersonateUser(user) {
        try {
            const { token } = await this.fetch.post('auth/impersonate', { user: user.id });
            await this.router.transitionTo('console');
            this.session.manuallyAuthenticate(token);
            this.notifications.info(`Now impersonating ${user.email}...`);
            later(() => window.location.reload(), 600);
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @action changeUserPassword(user) {
        this.modalsManager.show('modals/change-user-password', {
            keepOpen: true,
            user,
        });
    }

    @action async verifyUser(user) {
        return this.runUserLifecycleAction(user, 'verify', 'User verified.');
    }

    @action async activateUser(user) {
        return this.runUserLifecycleAction(user, 'activate', 'User activated.');
    }

    @action async deactivateUser(user) {
        return this.confirmUserLifecycleAction(
            user,
            this.intl.t('literal.console.deactivate-user'),
            this.intl.t('literal.console.deactivate-for-this-organization', { value: user.name || user.email }),
            'deactivate',
            this.intl.t('literal.console.user-deactivated')
        );
    }

    @action transferOwnership(user) {
        this.modalsManager.confirm({
            title: this.intl.t('literal.console.transfer-ownership'),
            body: this.intl.t('literal.console.transfer-organization-ownership-to', { value: user.name || user.email }),
            acceptButtonText: this.intl.t('literal.console.transfer-ownership'),
            acceptButtonIcon: 'crown',
            confirm: async (modal) => {
                modal.startLoading();

                try {
                    await this.fetch.post(`companies/${this.company.uuid}/transfer-ownership`, { newOwner: this.userIdentifier(user) });
                    this.notifications.success(this.intl.t('literal.console.organization-ownership-transferred'));
                    return this.router.refresh();
                } catch (error) {
                    this.notifications.serverError(error);
                }
            },
        });
    }

    @action removeUser(user) {
        return this.confirmUserLifecycleAction(
            user,
            this.intl.t('literal.console.remove-user'),
            this.intl.t('literal.console.remove-from-this-organization', { value: user.name || user.email }),
            null,
            this.intl.t('literal.console.user-removed'),
            async () => {
                await this.fetch.delete(`companies/${this.company.uuid}/users/${this.userIdentifier(user)}`);
            }
        );
    }

    @action search(event) {
        this.nestedQuery = event.target.value ?? '';
        this.nestedPage = 1;
    }

    @action refresh() {
        return this.router.refresh();
    }

    async runUserLifecycleAction(user, action, successMessage) {
        try {
            await this.fetch.patch(`companies/${this.company.uuid}/users/${this.userIdentifier(user)}/${action}`);
            this.notifications.success(successMessage);
            return this.router.refresh();
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    confirmUserLifecycleAction(user, title, body, action, successMessage, callback = null) {
        this.modalsManager.confirm({
            title,
            body,
            acceptButtonText: title,
            confirm: async (modal) => {
                modal.startLoading();

                try {
                    if (typeof callback === 'function') {
                        await callback();
                        this.notifications.success(successMessage);
                        return this.router.refresh();
                    }

                    return this.runUserLifecycleAction(user, action, successMessage);
                } catch (error) {
                    this.notifications.serverError(error);
                }
            },
        });
    }

    userIdentifier(user) {
        return user?.uuid || user?.id;
    }
}
