import EnergyResourceActionService from './energy-resource-action';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class DeliveryTicketActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-delivery-ticket', {
            resourceRoute: 'delivery-tickets',
            componentPrefix: 'energy-delivery-ticket',
            resourceKey: 'ticket',
            modelNamePath: 'public_id',
            defaultAttributes: { status: 'issued', unit: 'L', metered_qty: 0 },
        });
    }

    /**
     * Issue a ticket for an order / stop draw from the Fleet-Ops order panel.
     */
    issueForOrder(order, attributes = {}, options = {}) {
        return this.panel.create(
            {
                order,
                order_uuid: order?.id,
                ...attributes,
            },
            {
                title: this.intl.t('energyops.ticket.issue'),
                ...options,
            }
        );
    }

    /**
     * Header action buttons for a ticket panel (details route + context panel).
     */
    actionButtonsFor(ticket, options = {}) {
        const callback = options.callback ?? (() => ticket.reload());
        return [
            {
                icon: 'ellipsis-h',
                iconPrefix: 'fas',
                renderInPlace: true,
                items: [
                    {
                        text: this.intl.t('energyops.ticket.exception.short_fill'),
                        icon: 'arrow-down-short-wide',
                        permission: 'energy-ops update energy-delivery-ticket',
                        disabled: ticket.isVoid,
                        fn: () => this.recordException(ticket, 'short_fill', { callback }),
                    },
                    {
                        text: this.intl.t('energyops.ticket.exception.reject'),
                        icon: 'thumbs-down',
                        permission: 'energy-ops update energy-delivery-ticket',
                        disabled: ticket.isVoid,
                        fn: () => this.recordException(ticket, 'reject', { callback }),
                    },
                    {
                        text: this.intl.t('energyops.ticket.exception.contamination'),
                        icon: 'biohazard',
                        permission: 'energy-ops update energy-delivery-ticket',
                        disabled: ticket.isVoid,
                        fn: () => this.recordException(ticket, 'contamination', { callback }),
                    },
                    { separator: true },
                    {
                        text: this.intl.t('energyops.ticket.void'),
                        icon: 'ban',
                        permission: 'energy-ops void energy-delivery-ticket',
                        disabled: ticket.isVoid,
                        class: 'text-red-500',
                        fn: () => this.void(ticket, { callback }),
                    },
                    {
                        text: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.delivery-ticket') }),
                        icon: 'trash',
                        permission: 'energy-ops delete energy-delivery-ticket',
                        class: 'text-red-500',
                        fn: () => this.deleteAndReturn(ticket, options.deleteOptions ?? {}),
                    },
                ],
            },
        ];
    }

    @action void(ticket, options = {}) {
        return this.modalsManager.confirm({
            title: this.intl.t('energyops.ticket.void-title', { id: ticket.public_id }),
            body: this.intl.t('energyops.ticket.void-prompt'),
            acceptButtonText: this.intl.t('energyops.ticket.void'),
            acceptButtonType: 'danger',
            acceptButtonIcon: 'ban',
            confirm: async (modal) => {
                modal.startLoading();
                try {
                    const response = await this.postAction(`delivery-tickets/${ticket.id}/void`, { reason: modal.getOption('reason') });
                    this.store.pushPayload('energy-delivery-ticket', response);
                    this.notifications.success(this.intl.t('energyops.ticket.voided'));
                    modal.done();
                    if (typeof options.callback === 'function') options.callback(ticket);
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            ...options,
        });
    }

    /**
     * Record an exception (short fill, reject, contamination) against a ticket.
     */
    @action recordException(ticket, exceptionType, options = {}) {
        return this.modalsManager.show('modals/resource', {
            title: this.intl.t(`energyops.ticket.exception.${exceptionType}`),
            acceptButtonText: this.intl.t('common.confirm'),
            acceptButtonIcon: 'check',
            component: 'energy-delivery-ticket/exception-form',
            resource: { ticket, exception_type: exceptionType, reason: null },
            confirm: async (modal) => {
                modal.startLoading();
                const input = modal.getOption('resource');
                try {
                    const response = await this.postAction(`delivery-tickets/${ticket.id}/exception`, { exception_type: exceptionType, reason: input.reason });
                    this.store.pushPayload('energy-delivery-ticket', response);
                    this.notifications.success(this.intl.t('energyops.ticket.exception-recorded', { type: exceptionType.replace('_', ' ') }));
                    modal.done();
                    if (typeof options.callback === 'function') options.callback(ticket);
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
            ...options,
        });
    }

    @task *forOrder(order) {
        if (!order?.id) return [];
        const tickets = yield this.store.query('energy-delivery-ticket', { order_uuid: order.id, sort: '-created_at', limit: 50 });
        return tickets.slice ? tickets.slice() : [...tickets];
    }
}
