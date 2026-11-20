import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { TICKET_STATUSES, TICKET_EXCEPTION_TYPES } from '../../utils/energy-options';

const EXCEPTION_TYPES = TICKET_EXCEPTION_TYPES.map((option) => option.value);

export default class DeliveryTicketsIndexController extends Controller {
    @service deliveryTicketActions;
    @service tableContext;
    @service hostRouter;
    @service intl;

    queryParams = ['page', 'limit', 'sort', 'query', 'status', 'product', 'order_id', 'load_plan', 'exception_type', 'created_at'];
    @tracked query = null;
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked status;
    @tracked product;
    @tracked order_id;
    @tracked load_plan;
    @tracked exception_type;
    @tracked created_at;

    get actionButtons() {
        return [
            { icon: 'refresh', onClick: this.deliveryTicketActions.refresh, helpText: this.intl.t('common.refresh') },
            {
                text: this.intl.t('energyops.ticket.issue'),
                type: 'primary',
                icon: 'plus',
                onClick: this.deliveryTicketActions.transition.create,
                permission: 'energy-ops create energy-delivery-ticket',
            },
            { text: this.intl.t('common.export'), icon: 'long-arrow-up', iconClass: 'rotate-icon-45', wrapperClass: 'hidden md:flex', onClick: this.deliveryTicketActions.export },
        ];
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();
        return [{ label: this.intl.t('common.delete-selected-count', { count: selected.length }), class: 'text-red-500', fn: this.deliveryTicketActions.bulkDelete }];
    }

    get columns() {
        const label = this.intl.t('energyops.resource.delivery-ticket');
        return [
            {
                label: this.intl.t('column.id'),
                valuePath: 'public_id',
                width: '150px',
                cellComponent: 'table/cell/anchor',
                action: this.deliveryTicketActions.transition.view,
                permission: 'energy-ops view energy-delivery-ticket',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('energyops.ticket.fields.order'),
                valuePath: 'orderId',
                cellComponent: 'table/cell/anchor',
                action: (ticket) => ticket.order && this.hostRouter.transitionTo('console.fleet-ops.operations.orders.index.details', ticket.order),
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'order_id',
                model: 'order',
                modelNamePath: 'public_id',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-order'),
            },
            {
                label: this.intl.t('energyops.ticket.fields.load-plan'),
                valuePath: 'load_plan.public_id',
                cellComponent: 'table/cell/anchor',
                action: (ticket) => ticket.load_plan && this.hostRouter.transitionTo('console.energy-ops.load-plans.index.details', ticket.load_plan),
                resizable: true,
                hidden: true,
            },
            {
                label: this.intl.t('energyops.ticket.fields.product'),
                valuePath: 'productName',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'product',
                model: 'energy-product',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-product'),
            },
            { label: this.intl.t('energyops.ticket.fields.compartment'), valuePath: 'compartmentCode', width: '110px', resizable: true },
            { label: this.intl.t('energyops.ticket.fields.customer-tank'), valuePath: 'customerTankName', resizable: true, hidden: true },
            { label: this.intl.t('energyops.ticket.fields.planned'), valuePath: 'planned_qty', cellComponent: 'cell/energy-volume', unitPath: 'unit', width: '120px', resizable: true },
            {
                label: this.intl.t('energyops.ticket.fields.metered'),
                valuePath: 'metered_qty',
                cellComponent: 'cell/energy-volume',
                unitPath: 'unit',
                width: '120px',
                resizable: true,
                sortable: true,
                sortParam: 'metered_qty',
            },
            {
                label: this.intl.t('energyops.ticket.fields.variance'),
                valuePath: 'variance_qty',
                cellComponent: 'cell/energy-volume',
                unitPath: 'unit',
                width: '120px',
                resizable: true,
                sortable: true,
                sortParam: 'variance_qty',
            },
            {
                label: this.intl.t('energyops.ticket.fields.status'),
                valuePath: 'status',
                cellComponent: 'table/cell/status',
                width: '110px',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: TICKET_STATUSES,
            },
            {
                label: this.intl.t('energyops.ticket.fields.exception'),
                valuePath: 'exception_type',
                cellComponent: 'cell/energy-optional-status',
                width: '130px',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: EXCEPTION_TYPES,
            },
            {
                label: this.intl.t('energyops.ticket.fields.issued-at'),
                valuePath: 'issuedAt',
                sortParam: 'issued_at',
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'created_at',
                filterComponent: 'filter/date',
            },
            {
                label: '',
                cellComponent: 'table/cell/dropdown',
                ddButtonText: false,
                ddButtonIcon: 'ellipsis-h',
                ddButtonIconPrefix: 'fas',
                ddMenuLabel: this.intl.t('common.resource-actions', { resource: label }),
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                sticky: 'right',
                width: 60,
                actions: [
                    { label: this.intl.t('common.view-resource', { resource: label }), fn: this.deliveryTicketActions.transition.view, permission: 'energy-ops view energy-delivery-ticket' },
                    {
                        label: this.intl.t('energyops.ticket.exception.short_fill'),
                        fn: (ticket) => this.deliveryTicketActions.recordException(ticket, 'short_fill'),
                        permission: 'energy-ops update energy-delivery-ticket',
                        isVisible: (ticket) => !ticket.isVoid,
                    },
                    {
                        label: this.intl.t('energyops.ticket.exception.reject'),
                        fn: (ticket) => this.deliveryTicketActions.recordException(ticket, 'reject'),
                        permission: 'energy-ops update energy-delivery-ticket',
                        isVisible: (ticket) => !ticket.isVoid,
                    },
                    { separator: true },
                    {
                        label: this.intl.t('energyops.ticket.void'),
                        fn: this.deliveryTicketActions.void,
                        permission: 'energy-ops void energy-delivery-ticket',
                        isVisible: (ticket) => !ticket.isVoid,
                        class: 'text-red-500',
                    },
                    {
                        label: this.intl.t('common.delete-resource', { resource: label }),
                        fn: this.deliveryTicketActions.delete,
                        permission: 'energy-ops delete energy-delivery-ticket',
                        class: 'text-red-500',
                    },
                ],
                sortable: false,
                filterable: false,
                resizable: false,
                searchable: false,
            },
        ];
    }
}
