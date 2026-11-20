import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class FleetIndexController extends Controller {
    @service hostRouter;
    @service intl;
    @service compartmentActions;

    queryParams = ['page', 'limit', 'sort', 'query', 'status', 'plate_number', 'operation', 'configured'];
    @tracked query = null;
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked status;
    @tracked plate_number;
    @tracked operation;
    @tracked configured;

    get actionButtons() {
        return [
            { icon: 'refresh', onClick: this.compartmentActions.refresh, helpText: this.intl.t('common.refresh') },
            { text: this.intl.t('energyops.fleet.open-vehicle'), icon: 'truck', onClick: () => this.hostRouter.transitionTo('console.fleet-ops.management.vehicles.index') },
        ];
    }

    @action configure(vehicle) {
        return this.hostRouter.transitionTo('console.energy-ops.fleet.index.details', vehicle);
    }

    get columns() {
        return [
            {
                sticky: true,
                label: this.intl.t('energyops.fleet.columns.vehicle'),
                valuePath: 'displayName',
                photoPath: 'avatar_url',
                cellComponent: 'cell/energy-vehicle-identity',
                compact: true,
                action: this.configure,
                permission: 'energy-ops view energy-compartment',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
                filterParam: 'name',
            },
            {
                label: this.intl.t('energyops.fleet.columns.plate'),
                valuePath: 'plate_number',
                width: '120px',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
                filterParam: 'plate_number',
            },
            {
                label: this.intl.t('energyops.fleet.columns.operation'),
                valuePath: 'energy_summary.operation_name',
                cellComponent: 'table/cell/base',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'operation',
                model: 'energy-operation',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-operation'),
            },
            {
                label: this.intl.t('energyops.fleet.columns.profile'),
                valuePath: 'energy_summary.profile_status',
                cellComponent: 'table/cell/status',
                width: '120px',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterParam: 'configured',
                filterOptions: [
                    { label: this.intl.t('literal.energyops.configured'), value: 'yes' },
                    { label: this.intl.t('literal.energyops.not-configured'), value: 'no' },
                ],
                filterOptionLabel: 'label',
                filterOptionValue: 'value',
            },
            { label: this.intl.t('energyops.fleet.columns.compartments'), valuePath: 'energy_summary.compartments_count', width: '120px', resizable: true },
            {
                label: this.intl.t('energyops.fleet.columns.capacity'),
                valuePath: 'energy_summary.total_capacity',
                cellComponent: 'cell/energy-volume',
                unit: 'L',
                width: '140px',
                resizable: true,
            },
            {
                label: this.intl.t('column.status'),
                valuePath: 'status',
                cellComponent: 'table/cell/status',
                width: '110px',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterFetchOptions: 'vehicles/statuses',
            },
            {
                label: '',
                cellComponent: 'table/cell/dropdown',
                ddButtonText: false,
                ddButtonIcon: 'ellipsis-h',
                ddButtonIconPrefix: 'fas',
                ddMenuLabel: this.intl.t('common.resource-actions', { resource: this.intl.t('energyops.common.vehicle') }),
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                sticky: 'right',
                width: 60,
                actions: [
                    { label: this.intl.t('energyops.fleet.configure'), fn: this.configure, permission: 'energy-ops view energy-compartment' },
                    { label: this.intl.t('energyops.fleet.open-vehicle'), fn: (vehicle) => this.hostRouter.transitionTo('console.fleet-ops.management.vehicles.index.details', vehicle) },
                ],
                sortable: false,
                filterable: false,
                resizable: false,
                searchable: false,
            },
        ];
    }
}
