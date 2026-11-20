import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';

export default class OperationsIndexController extends Controller {
    @service operationActions;
    @service tableContext;
    @service intl;

    queryParams = ['page', 'limit', 'sort', 'query', 'status', 'family', 'created_at'];
    @tracked query = null;
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked status;
    @tracked family;
    @tracked created_at;

    get actionButtons() {
        return [
            { icon: 'refresh', onClick: this.operationActions.refresh, helpText: this.intl.t('common.refresh') },
            { text: this.intl.t('common.new'), type: 'primary', icon: 'plus', onClick: this.operationActions.transition.create, permission: 'energy-ops create energy-operation' },
            { text: this.intl.t('common.export'), icon: 'long-arrow-up', iconClass: 'rotate-icon-45', wrapperClass: 'hidden md:flex', onClick: this.operationActions.export },
        ];
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();
        return [{ label: this.intl.t('common.delete-selected-count', { count: selected.length }), class: 'text-red-500', fn: this.operationActions.bulkDelete }];
    }

    get columns() {
        return [
            {
                label: this.intl.t('energyops.common.code'),
                valuePath: 'code',
                width: '120px',
                cellComponent: 'table/cell/anchor',
                action: this.operationActions.transition.view,
                permission: 'energy-ops view energy-operation',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('energyops.common.name'),
                valuePath: 'name',
                cellComponent: 'table/cell/anchor',
                action: this.operationActions.transition.view,
                permission: 'energy-ops view energy-operation',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('energyops.common.family'),
                valuePath: 'family',
                cellComponent: 'table/cell/status',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: ['glp', 'wop', 'lubricants', 'other'],
            },
            { label: this.intl.t('energyops.operation.fields.depot'), valuePath: 'depotName', resizable: true },
            { label: this.intl.t('energyops.common.unit'), valuePath: 'unit', width: '80px', resizable: true },
            { label: this.intl.t('energyops.nav.products'), valuePath: 'products_count', width: '100px', resizable: true },
            {
                label: this.intl.t('energyops.common.status'),
                valuePath: 'status',
                cellComponent: 'table/cell/status',
                width: '110px',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: ['active', 'inactive'],
            },
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
                ddMenuLabel: this.intl.t('common.resource-actions', { resource: this.intl.t('energyops.resource.operation') }),
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                sticky: 'right',
                width: 60,
                actions: [
                    {
                        label: this.intl.t('common.view-resource', { resource: this.intl.t('energyops.resource.operation') }),
                        fn: this.operationActions.transition.view,
                        permission: 'energy-ops view energy-operation',
                    },
                    {
                        label: this.intl.t('common.edit-resource', { resource: this.intl.t('energyops.resource.operation') }),
                        fn: this.operationActions.transition.edit,
                        permission: 'energy-ops update energy-operation',
                    },
                    { separator: true },
                    {
                        label: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.operation') }),
                        fn: this.operationActions.delete,
                        permission: 'energy-ops delete energy-operation',
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
