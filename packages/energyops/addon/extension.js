import { MenuItem, Widget, ExtensionComponent } from '@fleetbase/ember-core/contracts';

const ENGINE = '@fleetbase/energyops-engine';

/**
 * Inject EnergyOps UI into Fleet-Ops slots. Fleet-Ops creates these registries in its own
 * `addon/extension.js#createRegistries`, so we wait for that engine before registering.
 */
function registerFleetOpsSlots(registryService) {
    // Vehicle form: energy profile + compartment editor
    registryService.registerRenderableComponent('fleet-ops:component:vehicle:form:after-details', new ExtensionComponent(ENGINE, 'energy-fleet-ops/vehicle-compartments'));
    // Vehicle details: read-only compartment inventory + schematic
    registryService.registerRenderableComponent('fleet-ops:component:vehicle:details', new ExtensionComponent(ENGINE, 'energy-fleet-ops/vehicle-compartment-inventory'));
    // Place form: customer tanks at this site
    registryService.registerRenderableComponent('fleet-ops:component:place:form', new ExtensionComponent(ENGINE, 'energy-fleet-ops/place-customer-tanks'));
    // Place form: energy role (depot / inventory) + receiving hours
    registryService.registerRenderableComponent('fleet-ops:component:place:form', new ExtensionComponent(ENGINE, 'energy-fleet-ops/place-energy-role'));
    // Order form: time window preset from the dropoff's receiving hours + auto first GLP/WOP line
    registryService.registerRenderableComponent('fleet-ops:component:order:form', new ExtensionComponent(ENGINE, 'energy-fleet-ops/order-energy-schedule'));
    // Order entity form: product + volume + unit for bulk lines
    registryService.registerRenderableComponent('fleet-ops:component:order:form:payload:entity:form', new ExtensionComponent(ENGINE, 'energy-fleet-ops/order-entity-energy-fields'));
    // Order details: load plan + tickets for this order
    registryService.registerRenderableComponent('fleet-ops:component:order:details', new ExtensionComponent(ENGINE, 'energy-fleet-ops/order-energy-panel'));
    // Orchestrator settings: compartment engine toggles
    registryService.registerRenderableComponent('fleet-ops:template:settings:orchestrator', new ExtensionComponent(ENGINE, 'energy-settings/orchestrator-energy'));
}

export default {
    setupExtension(app, universe) {
        const menuService = universe.getService('menu');
        const registryService = universe.getService('registry');
        const widgetService = universe.getService('widget');

        // 1. Header entry
        menuService.registerHeaderMenuItem('Energy Ops', 'console.energy-ops', {
            id: 'energy-ops',
            slug: 'energy-ops',
            icon: 'gas-pump',
            priority: 2,
            description: 'Compartment-aware LPG and white-oil tank dispatch.',
            shortcuts: [
                { title: 'Products', icon: 'flask', route: 'console.energy-ops.products', description: 'Energy product catalog.' },
                { title: 'Tank configuration', icon: 'truck-moving', route: 'console.energy-ops.fleet', description: 'Vehicle compartments and energy profiles.' },
                { title: 'Load plans', icon: 'clipboard-list', route: 'console.energy-ops.load-plans', description: 'Packed compartment load plans.' },
                { title: 'Delivery tickets', icon: 'receipt', route: 'console.energy-ops.delivery-tickets', description: 'Meter-fill delivery tickets.' },
                { title: 'Descargas', icon: 'arrow-down', route: 'console.energy-ops.unloads', description: 'End-of-day unloads to inventory.' },
            ],
        });

        // 2. Settings page (virtual route: /console/settings/energy-ops)
        menuService.registerSettingsMenuItem(
            new MenuItem({
                title: 'Energy Ops',
                slug: 'energy-ops',
                icon: 'gas-pump',
                view: 'index',
                component: new ExtensionComponent(ENGINE, 'energy-settings/energy-ops-settings'),
            })
        );

        // 3. Dashboard widget
        widgetService.registerWidgets('dashboard', [
            new Widget({
                id: 'energy-ops-key-metrics',
                name: 'Energy Ops Metrics',
                description: 'Open load plans, tickets issued, and fleet compartment capacity.',
                icon: 'gas-pump',
                component: new ExtensionComponent(ENGINE, 'energy-widget/key-metrics'),
                grid_options: { w: 12, h: 7, minW: 8, minH: 6 },
                options: { title: 'Energy Ops Metrics' },
            }),
        ]);

        // 4. Registries other extensions may plug into
        registryService.createRegistries([
            'energy-ops:component:load-plan:details',
            'energy-ops:component:delivery-ticket:details',
            'energy-ops:component:unload:details',
            'energy-ops:component:vehicle:panel',
        ]);

        // 5. Fleet-Ops integration
        universe.whenEngineLoaded('@fleetbase/fleetops-engine', () => registerFleetOpsSlots(registryService));
    },
};
