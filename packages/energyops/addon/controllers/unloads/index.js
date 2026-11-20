import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';

const UNLOAD_STATUSES = ['draft', 'confirmed', 'voided'];

export default class UnloadsIndexController extends Controller {
    @service unloadActions;
    @service tableContext;
    @service hostRouter;
    @service intl;

    queryParams = ['page', 'limit', 'sort', 'query', 'status', 'vehicle', 'inventory_place', 'load_plan', 'unloaded_at', 'created_at'];
    @tracked query = null;
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked status;
    @tracked vehicle;
    @tracked inventory_place;
    @tracked load_plan;
    @tracked unloaded_at;
    @tracked created_at;

    get actionButtons() {
        return [
            { icon: 'refresh', onClick: this.unloadActions.refresh, helpText: this.intl.t('common.refresh') },
            {
                text: this.intl.t('energyops.unload.register'),
                type: 'primary',
                icon: 'plus',
                onClick: this.unloadActions.transition.create,
                permission: 'energy-ops create energy-unload',
            },
            { text: this.intl.t('common.export'), icon: 'long-arrow-up', iconClass: 'rotate-icon-45', wrapperClass: 'hidden md:flex', onClick: this.unloadActions.export },
        ];
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();
        return [{ label: this.intl.t('common.delete-selected-count', { count: selected.length }), class: 'text-red-500', fn: this.unloadActions.bulkDelete }];
    }

    get columns() {
        const label = this.intl.t('energyops.resource.unload');
        return [
            {
                label: this.intl.t('column.id'),
                valuePath: 'public_id',
                width: '150px',
                cellComponent: 'table/cell/anchor',
                action: this.unloadActions.transition.view,
                permission: 'energy-ops view energy-unload',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('energyops.unload.fields.vehicle'),
                valuePath: 'vehicleName',
                cellComponent: 'table/cell/anchor',
                action: (unload) => unload.vehicle && this.hostRouter.transitionTo('console.energy-ops.fleet.index.details', unload.vehicle),
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'vehicle',
                model: 'vehicle',
                modelNamePath: 'displayName',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-vehicle'),
            },
            { label: this.intl.t('energyops.unload.fields.driver'), valuePath: 'driverName', resizable: true, hidden: true },
            {
                label: this.intl.t('energyops.unload.fields.inventory-place'),
                valuePath: 'inventoryPlaceName',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'inventory_place',
                model: 'place',
                modelNamePath: 'name',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-inventory-place'),
            },
            {
                label: this.intl.t('energyops.unload.fields.load-plan'),
                valuePath: 'load_plan.public_id',
                cellComponent: 'table/cell/anchor',
                action: (unload) => unload.load_plan?.public_id && this.hostRouter.transitionTo('console.energy-ops.load-plans.index.details', unload.load_plan.public_id),
                resizable: true,
            },
            {
                label: this.intl.t('energyops.unload.fields.before'),
                valuePath: 'total_before_volume',
                cellComponent: 'cell/energy-volume',
                unitPath: 'unit',
                width: '120px',
                resizable: true,
                hidden: true,
            },
            {
                label: this.intl.t('energyops.unload.fields.unloaded'),
                valuePath: 'total_unloaded_volume',
                cellComponent: 'cell/energy-volume',
                unitPath: 'unit',
                width: '130px',
                resizable: true,
                sortable: true,
                sortParam: 'total_unloaded_volume',
            },
            {
                label: this.intl.t('energyops.unload.fields.remaining'),
                valuePath: 'total_remaining_volume',
                cellComponent: 'cell/energy-volume',
                unitPath: 'unit',
                width: '130px',
                resizable: true,
                sortable: true,
                sortParam: 'total_remaining_volume',
            },
            {
                label: this.intl.t('energyops.unload.fields.status'),
                valuePath: 'status',
                cellComponent: 'table/cell/status',
                width: '110px',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: UNLOAD_STATUSES,
            },
            {
                label: this.intl.t('energyops.unload.fields.unloaded-at'),
                valuePath: 'unloadedAt',
                sortParam: 'unloaded_at',
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'unloaded_at',
                filterComponent: 'filter/date',
            },
            {
                label: this.intl.t('energyops.unload.fields.created-at'),
                valuePath: 'createdAt',
                sortParam: 'created_at',
                resizable: true,
                sortable: true,
                hidden: true,
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
                    { label: this.intl.t('common.view-resource', { resource: label }), fn: this.unloadActions.transition.view, permission: 'energy-ops view energy-unload' },
                    {
                        label: this.intl.t('energyops.unload.confirm'),
                        fn: this.unloadActions.confirm,
                        permission: 'energy-ops confirm energy-unload',
                        isVisible: (unload) => unload.isDraft,
                    },
                    { separator: true },
                    {
                        label: this.intl.t('energyops.unload.void'),
                        fn: this.unloadActions.void,
                        permission: 'energy-ops void energy-unload',
                        isVisible: (unload) => !unload.isVoided,
                        class: 'text-red-500',
                    },
                    {
                        label: this.intl.t('common.delete-resource', { resource: label }),
                        fn: this.unloadActions.delete,
                        permission: 'energy-ops delete energy-unload',
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
