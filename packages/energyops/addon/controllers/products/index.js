import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { HAZMAT_CLASSES, translateOptions } from '../../utils/energy-options';

export default class ProductsIndexController extends Controller {
    @service productActions;
    @service operationActions;
    @service tableContext;
    @service intl;

    queryParams = ['page', 'limit', 'sort', 'query', 'status', 'operation', 'hazmat_class', 'created_at'];
    @tracked query = null;
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked status;
    @tracked operation;
    @tracked hazmat_class;
    @tracked created_at;

    get actionButtons() {
        return [
            { icon: 'refresh', onClick: this.productActions.refresh, helpText: this.intl.t('common.refresh') },
            { text: this.intl.t('common.new'), type: 'primary', icon: 'plus', onClick: this.productActions.transition.create, permission: 'energy-ops create energy-product' },
            { text: this.intl.t('common.import'), type: 'magic', icon: 'upload', onClick: this.productActions.import, permission: 'energy-ops import energy-product' },
            {
                text: this.intl.t('common.export'),
                icon: 'long-arrow-up',
                iconClass: 'rotate-icon-45',
                wrapperClass: 'hidden md:flex',
                onClick: this.productActions.export,
                permission: 'energy-ops export energy-product',
            },
        ];
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();
        return [{ label: this.intl.t('common.delete-selected-count', { count: selected.length }), class: 'text-red-500', fn: this.productActions.bulkDelete }];
    }

    get columns() {
        return [
            {
                label: this.intl.t('energyops.common.code'),
                valuePath: 'code',
                width: '130px',
                cellComponent: 'table/cell/anchor',
                action: this.productActions.transition.view,
                permission: 'energy-ops view energy-product',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('energyops.common.name'),
                valuePath: 'name',
                cellComponent: 'table/cell/anchor',
                action: this.productActions.transition.view,
                permission: 'energy-ops view energy-product',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('energyops.resource.operation'),
                valuePath: 'operationName',
                cellComponent: 'table/cell/anchor',
                permission: 'energy-ops view energy-operation',
                action: (product) => product.operation && this.operationActions.panel.view(product.operation),
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-operation'),
                filterParam: 'operation',
                model: 'energy-operation',
                modelNamePath: 'name',
            },
            { label: this.intl.t('energyops.common.density'), valuePath: 'density', width: '110px', resizable: true, sortable: true },
            { label: this.intl.t('energyops.product.fields.un-number'), valuePath: 'un_number', width: '100px', resizable: true, hidden: true },
            {
                label: this.intl.t('energyops.product.fields.hazmat-class'),
                valuePath: 'hazmat_class',
                width: '110px',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: translateOptions(this.intl, HAZMAT_CLASSES),
                filterOptionLabel: 'label',
                filterOptionValue: 'value',
            },
            { label: this.intl.t('energyops.product.fields.default-unit'), valuePath: 'default_unit', width: '80px', resizable: true },
            { label: this.intl.t('energyops.product.fields.skill'), valuePath: 'skill', resizable: true, hidden: true },
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
                ddMenuLabel: this.intl.t('common.resource-actions', { resource: this.intl.t('energyops.resource.product') }),
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                sticky: 'right',
                width: 60,
                actions: [
                    {
                        label: this.intl.t('common.view-resource', { resource: this.intl.t('energyops.resource.product') }),
                        fn: this.productActions.transition.view,
                        permission: 'energy-ops view energy-product',
                    },
                    {
                        label: this.intl.t('common.edit-resource', { resource: this.intl.t('energyops.resource.product') }),
                        fn: this.productActions.transition.edit,
                        permission: 'energy-ops update energy-product',
                    },
                    { separator: true },
                    {
                        label: this.intl.t('common.delete-resource', { resource: this.intl.t('energyops.resource.product') }),
                        fn: this.productActions.delete,
                        permission: 'energy-ops delete energy-product',
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
