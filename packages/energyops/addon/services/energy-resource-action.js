import ResourceActionService, { inject as service } from '@fleetbase/ember-core/services/resource-action';
import { action } from '@ember/object';

export { service };

/**
 * Base action service for all EnergyOps resources.
 *
 * - Permission prefix is `energy-ops` (see server `Auth/Schemas/EnergyOps.php`)
 * - Mount prefix is `console.energy-ops`
 * - Provides generic `transition` + `panel` helpers driven by `resourceRoute` and `componentPrefix`.
 */
export default class EnergyResourceActionService extends ResourceActionService {
    @service fetch;

    /** Route segment for the index route of this resource, e.g. `products` */
    resourceRoute = null;

    /** Component folder for form/details components, e.g. `product` */
    componentPrefix = null;

    /** Translation key for the resource label e.g. `energyops.resource.product` */
    resourceLabelKey = null;

    /** Attribute name holding the record when opening panels (`product`, `operation` ...) */
    resourceKey = null;

    initializeEnergyResource(modelName, { resourceRoute, componentPrefix, resourceKey, resourceLabelKey, defaultAttributes = {}, modelNamePath = 'displayName' } = {}) {
        this.initialize(modelName, {
            defaultAttributes,
            modelNamePath,
            permissionPrefix: 'energy-ops',
            mountPrefix: 'console.energy-ops',
        });
        this.resourceRoute = resourceRoute;
        this.componentPrefix = componentPrefix;
        this.resourceKey = resourceKey ?? componentPrefix?.replace(/^energy-/, '').replace(/-/g, '_');
        this.resourceLabelKey = resourceLabelKey ?? `energyops.resource.${componentPrefix.replace(/^energy-/, '')}`;
        // ResourceActionService#initialize hard codes the fleet-ops prefix, correct it here.
        this.permissionPrefix = 'energy-ops';
        this.mountPrefix = 'console.energy-ops';
        return this;
    }

    /** Permission resources are named after the model (`energy-ops create energy-product`). */
    get permissionResource() {
        return this.modelName;
    }

    get resourceLabel() {
        return this.intl.t(this.resourceLabelKey);
    }

    can(actionName, resource = null) {
        return this.abilities.can(`energy-ops ${actionName} ${resource || this.permissionResource}`);
    }

    get savePermission() {
        return `energy-ops update ${this.permissionResource}`;
    }

    get createPermission() {
        return `energy-ops create ${this.permissionResource}`;
    }

    get deletePermission() {
        return `energy-ops delete ${this.permissionResource}`;
    }

    get viewPermission() {
        return `energy-ops view ${this.permissionResource}`;
    }

    /** Route transitions (deep-linkable panels rendered by nested routes). */
    transition = {
        view: (record) => this.transitionTo(`${this.resourceRoute}.index.details`, record),
        edit: (record) => this.transitionTo(`${this.resourceRoute}.index.edit`, record),
        create: () => this.transitionTo(`${this.resourceRoute}.index.new`),
        index: () => this.transitionTo(`${this.resourceRoute}.index`),
    };

    /** Host resource-context-panel overlays (used when opened from another context, e.g. Fleet-Ops). */
    get panel() {
        return {
            create: (attributes = {}, options = {}) => {
                const record = this.createNewInstance(attributes);
                return this.resourceContextPanel.open({
                    content: `${this.componentPrefix}/form`,
                    title: this.intl.t('common.create-a-new-resource', { resource: this.resourceLabel?.toLowerCase() }),
                    saveOptions: { callback: options.callback ?? this.refresh },
                    useDefaultSaveTask: true,
                    [this.resourceKey]: record,
                    resource: record,
                    ...options,
                });
            },
            edit: (record, options = {}) => {
                return this.resourceContextPanel.open({
                    content: `${this.componentPrefix}/form`,
                    title: this.intl.t('common.edit-resource-name', { resourceName: this.getRecordName(record) }),
                    saveOptions: { callback: options.callback ?? this.refresh },
                    useDefaultSaveTask: true,
                    [this.resourceKey]: record,
                    resource: record,
                    ...options,
                });
            },
            view: (record, options = {}) => {
                return this.resourceContextPanel.open({
                    content: `${this.componentPrefix}/details`,
                    title: this.getRecordName(record),
                    actionButtons: [
                        {
                            icon: 'pencil',
                            permission: this.savePermission,
                            fn: async () => {
                                await this.resourceContextPanel.closeAll();
                                this.panel.edit(record);
                            },
                        },
                    ],
                    [this.resourceKey]: record,
                    resource: record,
                    ...options,
                });
            },
        };
    }

    /**
     * Delete with a redirect back to the index (used from details/edit panels).
     */
    @action deleteAndReturn(record, options = {}) {
        return this.delete(record, {
            onConfirm: () => this.transition.index(),
            ...options,
        });
    }

    /**
     * POST to a custom action endpoint, e.g. `load-plans/{id}/confirm-load`.
     */
    async postAction(path, body = {}, options = {}) {
        return this.fetch.post(`${path}`, body, { namespace: 'energyops/int/v1', ...options });
    }

    async getAction(path, query = {}, options = {}) {
        return this.fetch.get(`${path}`, query, { namespace: 'energyops/int/v1', ...options });
    }
}
