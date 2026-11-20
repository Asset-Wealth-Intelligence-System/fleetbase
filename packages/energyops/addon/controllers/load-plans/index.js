import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { LOAD_PLAN_STATUSES } from '../../utils/energy-options';

export default class LoadPlansIndexController extends Controller {
    @service loadPlanActions;
    @service tableContext;
    @service hostRouter;
    @service intl;

    queryParams = ['page', 'limit', 'sort', 'query', 'status', 'vehicle', 'operation', 'created_at'];
    @tracked query = null;
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked status;
    @tracked vehicle;
    @tracked operation;
    @tracked created_at;

    get actionButtons() {
        return [
            { icon: 'refresh', onClick: this.loadPlanActions.refresh, helpText: this.intl.t('common.refresh') },
            {
                text: this.intl.t('energyops.load-plan.pack-orders'),
                type: 'primary',
                icon: 'boxes-packing',
                onClick: this.loadPlanActions.packOrders,
                permission: 'energy-ops pack energy-load-plan',
            },
            { text: this.intl.t('common.export'), icon: 'long-arrow-up', iconClass: 'rotate-icon-45', wrapperClass: 'hidden md:flex', onClick: this.loadPlanActions.export },
        ];
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();
        return [{ label: this.intl.t('common.delete-selected-count', { count: selected.length }), class: 'text-red-500', fn: this.loadPlanActions.bulkDelete }];
    }

    get columns() {
        const label = this.intl.t('energyops.resource.load-plan');
        return [
            {
                label: this.intl.t('column.id'),
                valuePath: 'public_id',
                width: '150px',
                cellComponent: 'table/cell/anchor',
                action: this.loadPlanActions.transition.view,
                permission: 'energy-ops view energy-load-plan',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('energyops.load-plan.fields.vehicle'),
                valuePath: 'vehicleName',
                cellComponent: 'table/cell/anchor',
                action: (plan) => plan.vehicle && this.hostRouter.transitionTo('console.energy-ops.fleet.index.details', plan.vehicle),
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'vehicle',
                model: 'vehicle',
                modelNamePath: 'displayName',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-vehicle'),
            },
            {
                label: this.intl.t('energyops.load-plan.fields.operation'),
                valuePath: 'operationName',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'operation',
                model: 'energy-operation',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-operation'),
            },
            {
                label: this.intl.t('energyops.load-plan.fields.status'),
                valuePath: 'status',
                cellComponent: 'table/cell/status',
                width: '120px',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: LOAD_PLAN_STATUSES,
            },
            { label: this.intl.t('energyops.load-plan.fields.orders'), valuePath: 'orders_count', width: '90px', resizable: true },
            { label: this.intl.t('energyops.load-plan.lines'), valuePath: 'lines.length', width: '90px', resizable: true },
            { label: this.intl.t('energyops.load-plan.fields.planned'), valuePath: 'plannedVolume', cellComponent: 'cell/energy-volume', unit: 'L', width: '130px', resizable: true },
            { label: this.intl.t('energyops.load-plan.fields.loaded'), valuePath: 'loadedVolume', cellComponent: 'cell/energy-volume', unit: 'L', width: '130px', resizable: true },
            { label: this.intl.t('energyops.load-plan.fields.packed-at'), valuePath: 'packedAt', sortParam: 'packed_at', resizable: true, sortable: true },
            { label: this.intl.t('energyops.load-plan.fields.loaded-at'), valuePath: 'loadedAt', sortParam: 'loaded_at', resizable: true, sortable: true, hidden: true },
            {
                label: this.intl.t('column.created-at'),
                valuePath: 'createdAt',
                sortParam: 'created_at',
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
                    { label: this.intl.t('common.view-resource', { resource: label }), fn: this.loadPlanActions.transition.view, permission: 'energy-ops view energy-load-plan' },
                    {
                        label: this.intl.t('energyops.load-plan.confirm-load'),
                        fn: (plan) => this.loadPlanActions.confirmLoad(plan),
                        permission: 'energy-ops confirm energy-load-plan',
                        isVisible: (plan) => plan.canConfirmLoad,
                    },
                    {
                        label: this.intl.t('energyops.load-plan.reload'),
                        fn: (plan) => this.loadPlanActions.reload.perform(plan),
                        permission: 'energy-ops reload energy-load-plan',
                        isVisible: (plan) => plan.isOpen,
                    },
                    {
                        label: this.intl.t('energyops.load-plan.close'),
                        fn: (plan) => this.loadPlanActions.close.perform(plan),
                        permission: 'energy-ops update energy-load-plan',
                        isVisible: (plan) => plan.canClose,
                    },
                    { separator: true },
                    {
                        label: this.intl.t('energyops.load-plan.cancel'),
                        fn: (plan) => this.loadPlanActions.cancel.perform(plan),
                        permission: 'energy-ops update energy-load-plan',
                        isVisible: (plan) => plan.isOpen,
                        class: 'text-red-500',
                    },
                    {
                        label: this.intl.t('common.delete-resource', { resource: label }),
                        fn: this.loadPlanActions.delete,
                        permission: 'energy-ops delete energy-load-plan',
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
