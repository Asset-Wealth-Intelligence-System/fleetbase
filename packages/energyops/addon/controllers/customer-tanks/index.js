import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { TANK_TYPES, translateOptions } from '../../utils/energy-options';

export default class CustomerTanksIndexController extends Controller {
    @service customerTankActions;
    @service productActions;
    @service tableContext;
    @service intl;

    queryParams = ['page', 'limit', 'sort', 'query', 'status', 'place', 'product', 'tank_type', 'needs_reorder', 'created_at'];
    @tracked query = null;
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked status;
    @tracked place;
    @tracked product;
    @tracked tank_type;
    @tracked needs_reorder;
    @tracked created_at;

    get actionButtons() {
        return [
            { icon: 'refresh', onClick: this.customerTankActions.refresh, helpText: this.intl.t('common.refresh') },
            { text: this.intl.t('common.new'), type: 'primary', icon: 'plus', onClick: this.customerTankActions.transition.create, permission: 'energy-ops create energy-customer-tank' },
            { text: this.intl.t('common.export'), icon: 'long-arrow-up', iconClass: 'rotate-icon-45', wrapperClass: 'hidden md:flex', onClick: this.customerTankActions.export },
        ];
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();
        return [{ label: this.intl.t('common.delete-selected-count', { count: selected.length }), class: 'text-red-500', fn: this.customerTankActions.bulkDelete }];
    }

    get columns() {
        const label = this.intl.t('energyops.resource.customer-tank');
        return [
            {
                label: this.intl.t('energyops.common.name'),
                valuePath: 'displayName',
                cellComponent: 'table/cell/anchor',
                action: this.customerTankActions.transition.view,
                permission: 'energy-ops view energy-customer-tank',
                resizable: true,
                sortable: true,
                sortParam: 'name',
                filterable: true,
                filterComponent: 'filter/string',
                filterParam: 'name',
            },
            {
                label: this.intl.t('energyops.tank.fields.place'),
                valuePath: 'placeName',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'place',
                model: 'place',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-place'),
            },
            {
                label: this.intl.t('energyops.tank.fields.product'),
                valuePath: 'productName',
                cellComponent: 'table/cell/anchor',
                permission: 'energy-ops view energy-product',
                action: (tank) => tank.product && this.productActions.panel.view(tank.product),
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'product',
                model: 'energy-product',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-product'),
            },
            {
                label: this.intl.t('energyops.tank.fields.type'),
                valuePath: 'tank_type',
                cellComponent: 'table/cell/status',
                width: '130px',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: translateOptions(this.intl, TANK_TYPES),
                filterOptionLabel: 'label',
                filterOptionValue: 'value',
            },
            {
                label: this.intl.t('energyops.tank.fields.capacity'),
                valuePath: 'capacity_volume',
                cellComponent: 'cell/energy-volume',
                unitPath: 'unit',
                width: '120px',
                resizable: true,
                sortable: true,
            },
            {
                label: this.intl.t('energyops.tank.fields.last-known'),
                valuePath: 'last_known_volume',
                cellComponent: 'cell/energy-volume',
                unitPath: 'unit',
                width: '120px',
                resizable: true,
                sortable: true,
            },
            { label: this.intl.t('energyops.common.fill'), valuePath: 'fillPercent', cellComponent: 'cell/energy-fill-level', width: '140px', resizable: true },
            { label: this.intl.t('energyops.tank.ullage'), valuePath: 'computedUllage', cellComponent: 'cell/energy-volume', unitPath: 'unit', width: '120px', resizable: true },
            {
                label: this.intl.t('energyops.tank.fields.reorder-point'),
                valuePath: 'reorder_point',
                cellComponent: 'cell/energy-volume',
                unitPath: 'unit',
                width: '120px',
                resizable: true,
                hidden: true,
            },
            { label: this.intl.t('energyops.tank.fields.serial'), valuePath: 'serial_number', resizable: true, hidden: true },
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
            { label: this.intl.t('column.created-at'), valuePath: 'createdAt', sortParam: 'created_at', resizable: true, sortable: true, hidden: true },
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
                    { label: this.intl.t('common.view-resource', { resource: label }), fn: this.customerTankActions.transition.view, permission: 'energy-ops view energy-customer-tank' },
                    { label: this.intl.t('common.edit-resource', { resource: label }), fn: this.customerTankActions.transition.edit, permission: 'energy-ops update energy-customer-tank' },
                    { separator: true },
                    {
                        label: this.intl.t('common.delete-resource', { resource: label }),
                        fn: this.customerTankActions.delete,
                        permission: 'energy-ops delete energy-customer-tank',
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
