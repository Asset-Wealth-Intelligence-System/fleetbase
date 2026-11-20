import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { COMPATIBILITY_ACTIONS, translateOptions } from '../../utils/energy-options';

export default class CompatibilityRulesIndexController extends Controller {
    @service compatibilityRuleActions;
    @service tableContext;
    @service intl;

    queryParams = ['page', 'limit', 'sort', 'query', 'action', 'from_product', 'to_product', 'status'];
    @tracked query = null;
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked action;
    @tracked from_product;
    @tracked to_product;
    @tracked status;

    get actionButtons() {
        return [
            { icon: 'refresh', onClick: this.compatibilityRuleActions.refresh, helpText: this.intl.t('common.refresh') },
            {
                text: this.intl.t('common.new'),
                type: 'primary',
                icon: 'plus',
                onClick: this.compatibilityRuleActions.transition.create,
                permission: 'energy-ops create energy-compatibility-rule',
            },
        ];
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();
        return [{ label: this.intl.t('common.delete-selected-count', { count: selected.length }), class: 'text-red-500', fn: this.compatibilityRuleActions.bulkDelete }];
    }

    get columns() {
        const label = this.intl.t('energyops.resource.compatibility-rule');
        return [
            {
                label: this.intl.t('energyops.compatibility.fields.from-product'),
                valuePath: 'fromLabel',
                cellComponent: 'table/cell/anchor',
                action: this.compatibilityRuleActions.transition.view,
                permission: 'energy-ops view energy-compatibility-rule',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'from_product',
                model: 'energy-product',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-product'),
            },
            {
                label: this.intl.t('energyops.compatibility.fields.to-product'),
                valuePath: 'toLabel',
                cellComponent: 'table/cell/anchor',
                action: this.compatibilityRuleActions.transition.view,
                permission: 'energy-ops view energy-compatibility-rule',
                resizable: true,
                filterable: true,
                filterComponent: 'filter/model',
                filterParam: 'to_product',
                model: 'energy-product',
                filterComponentPlaceholder: this.intl.t('energyops.common.select-product'),
            },
            {
                label: this.intl.t('energyops.compatibility.fields.action'),
                valuePath: 'action',
                cellComponent: 'table/cell/status',
                width: '150px',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: translateOptions(this.intl, COMPATIBILITY_ACTIONS),
                filterOptionLabel: 'label',
                filterOptionValue: 'value',
            },
            { label: this.intl.t('energyops.compatibility.fields.flush-volume'), valuePath: 'flush_volume', width: '110px', resizable: true },
            { label: this.intl.t('energyops.compatibility.fields.same-family'), valuePath: 'same_family_only', cellComponent: 'table/cell/checkbox', width: '110px', resizable: true },
            { label: this.intl.t('energyops.compatibility.fields.notes'), valuePath: 'notes', resizable: true, hidden: true },
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
            { label: this.intl.t('column.created-at'), valuePath: 'createdAt', sortParam: 'created_at', resizable: true, sortable: true },
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
                    {
                        label: this.intl.t('common.view-resource', { resource: label }),
                        fn: this.compatibilityRuleActions.transition.view,
                        permission: 'energy-ops view energy-compatibility-rule',
                    },
                    {
                        label: this.intl.t('common.edit-resource', { resource: label }),
                        fn: this.compatibilityRuleActions.transition.edit,
                        permission: 'energy-ops update energy-compatibility-rule',
                    },
                    { separator: true },
                    {
                        label: this.intl.t('common.delete-resource', { resource: label }),
                        fn: this.compatibilityRuleActions.delete,
                        permission: 'energy-ops delete energy-compatibility-rule',
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
