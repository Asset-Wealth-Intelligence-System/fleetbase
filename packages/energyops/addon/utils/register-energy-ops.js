import CompartmentEditor from '../components/energy-compartment/editor';
import CompartmentForm from '../components/energy-compartment/form';
import CompartmentInventory from '../components/energy-compartment/inventory';
import CompartmentSchematic from '../components/energy-compartment/schematic';
import VehicleProfileForm from '../components/energy-vehicle-profile/form';
import CustomerTankForm from '../components/energy-customer-tank/form';
import CustomerTankDetails from '../components/energy-customer-tank/details';
import DeliveryTicketList from '../components/energy-delivery-ticket/list';
import DeliveryTicketForm from '../components/energy-delivery-ticket/form';
import DeliveryTicketDetails from '../components/energy-delivery-ticket/details';
import DeliveryTicketExceptionForm from '../components/energy-delivery-ticket/exception-form';
import LoadPlanDetails from '../components/energy-load-plan/details';
import LoadPlanPackForm from '../components/energy-load-plan/pack-form';
import LoadPlanConfirmLoadForm from '../components/energy-load-plan/confirm-load-form';
import LoadPlanHistory from '../components/energy-load-plan/history';
import LoadPlanAddOrdersForm from '../components/energy-load-plan/add-orders-form';
import UnloadForm from '../components/energy-unload/form';
import UnloadList from '../components/energy-unload/list';
import UnloadDetails from '../components/energy-unload/details';
import ProofFiles from '../components/energy-fleet-ops/proof-files';
import PlaceEnergyRole from '../components/energy-fleet-ops/place-energy-role';
import OrderEnergySchedule from '../components/energy-fleet-ops/order-energy-schedule';
import CellFillLevel from '../components/cell/energy-fill-level';
import CellOptionalStatus from '../components/cell/energy-optional-status';
import CellVehicleIdentity from '../components/cell/energy-vehicle-identity';
import CellVolume from '../components/cell/energy-volume';
import CompartmentActionsService from '../services/compartment-actions';
import CompatibilityRuleActionsService from '../services/compatibility-rule-actions';
import CustomerTankActionsService from '../services/customer-tank-actions';
import DeliveryTicketActionsService from '../services/delivery-ticket-actions';
import LoadPlanActionsService from '../services/load-plan-actions';
import OperationActionsService from '../services/operation-actions';
import ProductActionsService from '../services/product-actions';
import VehicleProfileActionsService from '../services/vehicle-profile-actions';
import UnloadActionsService from '../services/unload-actions';
import EnergyOnChangeHelper from '../helpers/energy-on-change';

/**
 * Components rendered by the Energy Ops slot components (things Fleet-Ops templates end up rendering).
 * Keyed by the invocation name used in templates / `cellComponent` column definitions.
 */
const COMPONENTS = {
    'energy-compartment/editor': CompartmentEditor,
    'energy-compartment/form': CompartmentForm,
    'energy-compartment/inventory': CompartmentInventory,
    'energy-compartment/schematic': CompartmentSchematic,
    'energy-vehicle-profile/form': VehicleProfileForm,
    'energy-customer-tank/form': CustomerTankForm,
    'energy-customer-tank/details': CustomerTankDetails,
    'energy-delivery-ticket/list': DeliveryTicketList,
    'energy-delivery-ticket/form': DeliveryTicketForm,
    'energy-delivery-ticket/details': DeliveryTicketDetails,
    'energy-delivery-ticket/exception-form': DeliveryTicketExceptionForm,
    'energy-load-plan/details': LoadPlanDetails,
    'energy-load-plan/pack-form': LoadPlanPackForm,
    'energy-load-plan/confirm-load-form': LoadPlanConfirmLoadForm,
    'energy-load-plan/history': LoadPlanHistory,
    'energy-load-plan/add-orders-form': LoadPlanAddOrdersForm,
    'energy-unload/form': UnloadForm,
    'energy-unload/list': UnloadList,
    'energy-unload/details': UnloadDetails,
    'energy-fleet-ops/proof-files': ProofFiles,
    'energy-fleet-ops/place-energy-role': PlaceEnergyRole,
    'energy-fleet-ops/order-energy-schedule': OrderEnergySchedule,
    'cell/energy-fill-level': CellFillLevel,
    'cell/energy-optional-status': CellOptionalStatus,
    'cell/energy-vehicle-identity': CellVehicleIdentity,
    'cell/energy-volume': CellVolume,
};

/** Helpers used by the slot component templates. */
const HELPERS = {
    'energy-on-change': EnergyOnChangeHelper,
};

/** Energy Ops services injected by the components above. */
const SERVICES = {
    'compartment-actions': CompartmentActionsService,
    'compatibility-rule-actions': CompatibilityRuleActionsService,
    'customer-tank-actions': CustomerTankActionsService,
    'delivery-ticket-actions': DeliveryTicketActionsService,
    'load-plan-actions': LoadPlanActionsService,
    'operation-actions': OperationActionsService,
    'product-actions': ProductActionsService,
    'vehicle-profile-actions': VehicleProfileActionsService,
    'unload-actions': UnloadActionsService,
};

/**
 * Make the Energy Ops components and services resolvable from another owner.
 *
 * Ember engines are isolated containers: when Fleet-Ops renders one of our slot components
 * (via `RegistryYield` → `LazyEngineComponent`) the component class comes from this engine but it is
 * instantiated with the *Fleet-Ops* engine instance as owner. Anything it injects (`@service compartmentActions`)
 * or invokes (`<EnergyCompartment::Editor />`) is then looked up in Fleet-Ops' registry, where it does not exist.
 *
 * This mirrors Fleet-Ops' own `registerComponent(owner, ...)` pattern for the components it renders inside
 * other engines. Registration is idempotent and only adds entries that are missing, so calling it from the
 * host app (dashboard widgets, resource context panel) is a no-op.
 *
 * @param {Owner} owner the owner the slot component was instantiated with (`getOwner(this)`)
 */
export default function registerEnergyOps(owner) {
    if (!owner || typeof owner.register !== 'function' || typeof owner.hasRegistration !== 'function') {
        return;
    }

    for (const [name, componentClass] of Object.entries(COMPONENTS)) {
        const key = `component:${name}`;
        if (!owner.hasRegistration(key)) {
            owner.register(key, componentClass);
        }
    }

    for (const [name, helperClass] of Object.entries(HELPERS)) {
        const key = `helper:${name}`;
        if (!owner.hasRegistration(key)) {
            owner.register(key, helperClass);
        }
    }

    for (const [name, serviceClass] of Object.entries(SERVICES)) {
        const key = `service:${name}`;
        if (!owner.hasRegistration(key)) {
            owner.register(key, serviceClass);
        }
    }
}
