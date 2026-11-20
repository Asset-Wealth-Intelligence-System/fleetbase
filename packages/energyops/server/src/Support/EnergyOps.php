<?php

namespace Fleetbase\EnergyOps\Support;

use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\EnergyOps\Models\Product;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\OrderConfig;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\Models\Company;
use Fleetbase\Models\Permission;
use Fleetbase\Models\Policy;
use Fleetbase\Models\Role;
use Fleetbase\Models\Setting;
use Illuminate\Support\Str;

class EnergyOps
{
    public const GLP_CONFIG_KEY = 'glp-delivery';
    public const WOP_CONFIG_KEY = 'wop-delivery';
    public const GLP_CONFIG_NS  = 'energyops:glp-delivery';
    public const WOP_CONFIG_NS  = 'energyops:wop-delivery';

    /** Volume / mass units accepted on products, compartments, tanks and tickets. */
    public const UNITS = ['L', 'gal', 'kg', 'm3'];

    /** Identifier of the compartment orchestration engine registered with Fleet-Ops. */
    public const ENGINE_ID = 'compartment';

    /** Fleet-Ops `places.type` values used by EnergyOps for loading and unloading sites. */
    public const PLACE_TYPE_DEPOT     = 'depot';
    public const PLACE_TYPE_INVENTORY = 'inventory';

    /** Safety setting keys a company may override from the Energy Ops settings page. */
    public const SAFETY_KEYS = ['axle_balance', 'surge_band', 'min_fill_ratio', 'max_fill_ratio', 'variance_repack_threshold', 'require_load_plan', 'require_load_proof', 'require_unload_proof'];

    /**
     * Whether EnergyOps is enabled for a company.
     */
    public static function enabled(Company|string|null $company = null): bool
    {
        $companyUuid = static::companyUuid($company);
        if ($companyUuid) {
            $flag = Setting::lookup('company.' . $companyUuid . '.energyops.enabled', null);
            if ($flag !== null) {
                return (bool) $flag;
            }
        }

        return (bool) config('energyops.enabled', true);
    }

    /**
     * Company-level safety / variance settings merged over the package config.
     */
    public static function safetySettings(Company|string|null $company = null): array
    {
        $defaults = [
            'axle_balance'              => (bool) config('energyops.safety.axle_balance', false),
            'surge_band'                => (bool) config('energyops.safety.surge_band', false),
            'min_fill_ratio'            => (float) config('energyops.safety.min_fill_ratio', 0.2),
            'max_fill_ratio'            => (float) config('energyops.safety.max_fill_ratio', 0.8),
            'variance_repack_threshold' => (float) config('energyops.variance_repack_threshold', 0.15),
            'require_load_plan'         => (bool) config('energyops.safety.require_load_plan', true),
            'require_load_proof'        => (bool) config('energyops.safety.require_load_proof', true),
            'require_unload_proof'      => (bool) config('energyops.safety.require_unload_proof', true),
        ];

        $companyUuid = static::companyUuid($company);
        if (!$companyUuid) {
            return $defaults;
        }

        $stored = Setting::lookup('company.' . $companyUuid . '.energyops.safety', []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Whether an energy order may only be dispatched once it sits on a packed load plan.
     */
    public static function requireLoadPlanForDispatch(Company|string|null $company = null): bool
    {
        return (bool) (static::safetySettings($company)['require_load_plan'] ?? true);
    }

    /**
     * Whether confirming a load requires at least one attachment (factura / boleta de carga / foto).
     */
    public static function requireLoadProof(Company|string|null $company = null): bool
    {
        return (bool) (static::safetySettings($company)['require_load_proof'] ?? true);
    }

    /**
     * Whether confirming a descarga requires at least one attachment (foto / firma).
     */
    public static function requireUnloadProof(Company|string|null $company = null): bool
    {
        return (bool) (static::safetySettings($company)['require_unload_proof'] ?? true);
    }

    /**
     * Variance ratio (0-1) above which a ticket is flagged and the order may need re-planning.
     */
    public static function varianceThreshold(Company|string|null $company = null): float
    {
        $threshold = (float) (static::safetySettings($company)['variance_repack_threshold'] ?? 0.15);

        return $threshold > 0 ? $threshold : 0.15;
    }

    public static function saveSafetySettings(Company|string $company, array $settings): array
    {
        $companyUuid = static::companyUuid($company);
        if (!$companyUuid) {
            return static::safetySettings();
        }

        $merged = array_merge(static::safetySettings($companyUuid), array_intersect_key($settings, array_flip(static::SAFETY_KEYS)));
        Setting::configure('company.' . $companyUuid . '.energyops.safety', $merged);

        return $merged;
    }

    /**
     * Convert a quantity in any supported unit to liters.
     */
    public static function toLiters(float $qty, string $unit = 'L', ?Product $product = null): float
    {
        $unit = Str::lower($unit);
        if (in_array($unit, ['gal', 'gallon', 'gallons'], true)) {
            return $qty * 3.785411784;
        }
        if (in_array($unit, ['m3', 'm³', 'cbm'], true)) {
            return $qty * 1000;
        }

        return static::litersFromQuantity($qty, $unit, $product);
    }

    /**
     * Convert liters back into `$unit` (inverse of toLiters). Mass units use the product density.
     */
    public static function fromLiters(float $liters, string $unit = 'L', ?Product $product = null): float
    {
        $unit = Str::lower($unit);
        if (in_array($unit, ['gal', 'gallon', 'gallons'], true)) {
            return $liters / 3.785411784;
        }
        if (in_array($unit, ['m3', 'm³', 'cbm'], true)) {
            return $liters / 1000;
        }
        if (in_array($unit, ['kg', 'kgs', 'kilogram', 'kilograms'], true)) {
            $density = (float) ($product?->density ?: 0.54);

            return $liters * ($density > 0 ? $density : 0.54);
        }

        return $liters;
    }

    public static function enableForCompany(Company|string $company, bool $enabled = true): void
    {
        $companyUuid = static::companyUuid($company);
        if (!$companyUuid) {
            return;
        }

        Setting::configure('company.' . $companyUuid . '.energyops.enabled', $enabled);
    }

    /**
     * Ensure Energy Ops is visible in the console for company administrators.
     * `fleetbase:create-permissions` creates the catalog; this attaches it to
     * the company Administrator role so Smart Nav's `see extension` check passes.
     */
    public static function grantCompanyAccess(Company|string $company): void
    {
        $companyUuid = static::companyUuid($company);
        if (!$companyUuid) {
            return;
        }

        $guard = 'sanctum';
        $see   = Permission::updateOrCreate(
            [
                'name'       => 'energy-ops see extension',
                'guard_name' => $guard,
            ],
            [
                'name'       => 'energy-ops see extension',
                'guard_name' => $guard,
                'service'    => 'energy-ops',
            ]
        );

        $administratorAccess = Policy::where('name', 'AdministratorAccess')->where('guard_name', $guard)->first();
        if ($administratorAccess) {
            $administratorAccess->givePermissionTo($see);
        }

        $manager = Policy::where('name', 'EnergyOpsManager')->where('guard_name', $guard)->first();

        $roles = Role::query()
            ->where('name', 'Administrator')
            ->where('guard_name', $guard)
            ->where(function ($query) use ($companyUuid) {
                $query->whereNull('company_uuid')->orWhere('company_uuid', $companyUuid);
            })
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($see);
            if ($administratorAccess) {
                $role->assignPolicy($administratorAccess);
            }
            if ($manager) {
                $role->assignPolicy($manager);
            }
        }
    }

    /**
     * Company allocation settings stored by FleetOps SettingController.
     */
    public static function allocationSettings(Company|string|null $company = null): array
    {
        $defaults = [
            'allocation_engine'           => 'compartment',
            'auto_allocate_on_create'     => false,
            'auto_reallocate_on_complete' => false,
            'max_travel_time_seconds'     => 3600,
            'balance_workload'            => false,
        ];

        $companyUuid = static::companyUuid($company);
        if (!$companyUuid) {
            return $defaults;
        }

        $stored = Setting::lookup('company.' . $companyUuid . '.fleet-ops.allocation-settings', []);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public static function setAllocationEngine(Company|string $company, string $engine = 'compartment'): void
    {
        $companyUuid = static::companyUuid($company);
        if (!$companyUuid) {
            return;
        }

        $settings                      = static::allocationSettings($companyUuid);
        $settings['allocation_engine'] = $engine;
        Setting::configure('company.' . $companyUuid . '.fleet-ops.allocation-settings', $settings);
        Setting::configure('fleetops.orchestrator_engine', $engine);
    }

    public static function isEnergyOrder(?Order $order): bool
    {
        if (!$order) {
            return false;
        }

        $namespace = data_get($order, 'orderConfig.namespace') ?? $order->getMeta('energyops_config');
        if (is_string($namespace) && Str::startsWith($namespace, 'energyops:')) {
            return true;
        }

        $key = data_get($order, 'orderConfig.key');

        return in_array($key, [static::GLP_CONFIG_KEY, static::WOP_CONFIG_KEY], true);
    }

    public static function orderOperation(?Order $order): ?Operation
    {
        if (!$order) {
            return null;
        }

        $uuid = $order->getMeta('energyops_operation_uuid');
        if ($uuid) {
            return Operation::where('uuid', $uuid)->first();
        }

        $code = $order->getMeta('energyops_operation') ?? static::operationCodeFromConfig($order);
        if (!$code) {
            return null;
        }

        return Operation::where('company_uuid', $order->company_uuid)->where('code', $code)->first();
    }

    public static function operationCodeFromConfig(?Order $order): ?string
    {
        $namespace = data_get($order, 'orderConfig.namespace');
        if ($namespace === static::GLP_CONFIG_NS) {
            return 'glp';
        }
        if ($namespace === static::WOP_CONFIG_NS) {
            return 'wop';
        }

        $key = data_get($order, 'orderConfig.key');
        if ($key === static::GLP_CONFIG_KEY) {
            return 'glp';
        }
        if ($key === static::WOP_CONFIG_KEY) {
            return 'wop';
        }

        return $order?->getMeta('energyops_operation');
    }

    public static function productSkills(Product $product): array
    {
        $skills = array_filter([
            data_get($product, 'operation.code'),
            $product->code,
            $product->skill,
        ]);

        return array_values(array_unique(array_map(fn ($s) => Str::slug((string) $s, '_'), $skills)));
    }

    public static function litersFromQuantity(float $qty, string $unit, ?Product $product = null): float
    {
        $unit = Str::lower($unit);
        if (in_array($unit, ['l', 'lt', 'ltr', 'liter', 'litre', 'liters', 'litres'], true)) {
            return $qty;
        }

        if (in_array($unit, ['kg', 'kgs', 'kilogram', 'kilograms'], true)) {
            $density = (float) ($product?->density ?: 0.54);
            if ($density <= 0) {
                $density = 0.54;
            }

            return $qty / $density;
        }

        return $qty;
    }

    public static function volumeAsCubeMeters(float $liters): array
    {
        $m3   = max($liters, 0) / 1000;
        $side = $m3 > 0 ? $m3 ** (1 / 3) : 0;

        return [
            'length'           => $side,
            'width'            => $side,
            'height'           => $side,
            'dimensions_unit'  => 'm',
        ];
    }

    /**
     * Resolve a place of the given type (`depot` / `inventory`) by uuid or public id within a company.
     */
    public static function placeOfType(Company|string|null $company, ?string $id, string $type): ?Place
    {
        if (!$id) {
            return null;
        }

        $companyUuid = static::companyUuid($company);

        return Place::query()
            ->where('company_uuid', $companyUuid)
            ->where('type', $type)
            ->where(fn ($q) => $q->where('uuid', $id)->orWhere('public_id', $id))
            ->first();
    }

    /**
     * Habitual loading station for a vehicle: the energy profile default, else the operation depot.
     */
    public static function defaultDepotForVehicle(Vehicle|string|null $vehicle, ?Operation $operation = null): ?Place
    {
        $vehicleUuid = $vehicle instanceof Vehicle ? $vehicle->uuid : $vehicle;
        if ($vehicleUuid) {
            $profile = VehicleProfile::query()->where('vehicle_uuid', $vehicleUuid)->with(['defaultDepot', 'operation'])->first();
            if ($profile?->defaultDepot) {
                return $profile->defaultDepot;
            }
            $operation = $operation ?? $profile?->operation;
        }

        if ($operation?->depot_place_uuid) {
            return Place::where('uuid', $operation->depot_place_uuid)->first();
        }

        return null;
    }

    /**
     * The company inventory site where descargas are received (single `inventory` place, or the flagged one).
     */
    public static function inventoryPlace(Company|string|null $company = null): ?Place
    {
        $companyUuid = static::companyUuid($company);
        if (!$companyUuid) {
            return null;
        }

        $preferred = Setting::lookup('company.' . $companyUuid . '.energyops.inventory_place_uuid', null);
        if ($preferred) {
            $place = Place::where('company_uuid', $companyUuid)->where('uuid', $preferred)->first();
            if ($place) {
                return $place;
            }
        }

        return Place::query()
            ->where('company_uuid', $companyUuid)
            ->where('type', static::PLACE_TYPE_INVENTORY)
            ->orderBy('created_at')
            ->first();
    }

    public static function setInventoryPlace(Company|string $company, ?string $placeUuid): void
    {
        $companyUuid = static::companyUuid($company);
        if (!$companyUuid) {
            return;
        }

        Setting::configure('company.' . $companyUuid . '.energyops.inventory_place_uuid', $placeUuid);
    }

    public static function activeLoadPlanForOrder(Order $order): ?LoadPlan
    {
        return LoadPlan::query()
            ->where('company_uuid', $order->company_uuid)
            ->whereIn('status', ['draft', 'packed', 'loaded', 'in_transit'])
            ->whereHas('lines', fn ($q) => $q->where('order_uuid', $order->uuid))
            ->latest()
            ->first();
    }

    public static function createOrderConfigs(Company|string $company): array
    {
        $companyUuid = static::companyUuid($company);
        $glp         = static::firstOrCreateConfig($companyUuid, static::GLP_CONFIG_KEY, static::GLP_CONFIG_NS, 'GLP Delivery', 'Meter-fill GLP bulk delivery');
        $wop         = static::firstOrCreateConfig($companyUuid, static::WOP_CONFIG_KEY, static::WOP_CONFIG_NS, 'WOP Delivery', 'Meter-fill white-oil bulk delivery');

        static::syncDeliveryFlow($glp);
        static::syncDeliveryFlow($wop);

        return [$glp, $wop];
    }

    /**
     * Bring an existing config's flow up to date with `deliveryFlow()` without touching user-added
     * activities: known steps get their POD requirements / details refreshed, unknown steps are kept.
     * Idempotent — running `energyops:install` twice yields the same flow.
     */
    public static function syncDeliveryFlow(OrderConfig $config): OrderConfig
    {
        $current = is_array($config->flow) ? $config->flow : [];
        $target  = static::deliveryFlow();
        $changed = false;

        foreach ($target as $key => $step) {
            $existing = $current[$key] ?? null;
            if (!is_array($existing)) {
                $current[$key] = $step;
                $changed       = true;
                continue;
            }

            foreach (['require_pod', 'pod_method', 'details', 'complete'] as $field) {
                if (($existing[$field] ?? null) !== $step[$field]) {
                    $existing[$field] = $step[$field];
                    $changed          = true;
                }
            }

            // union of activities so operators keep custom transitions
            $activities = array_values(array_unique(array_merge((array) ($existing['activities'] ?? []), $step['activities'])));
            if ($activities !== (array) ($existing['activities'] ?? [])) {
                $existing['activities'] = $activities;
                $changed                = true;
            }

            $current[$key] = $existing;
        }

        if ($changed) {
            $config->flow = $current;
            $config->save();
        }

        return $config;
    }

    protected static function firstOrCreateConfig(string $companyUuid, string $key, string $namespace, string $name, string $description): OrderConfig
    {
        return OrderConfig::firstOrCreate(
            [
                'company_uuid' => $companyUuid,
                'key'          => $key,
                'namespace'    => $namespace,
            ],
            [
                'name'         => $name,
                'key'          => $key,
                'namespace'    => $namespace,
                'description'  => $description,
                'core_service' => 0,
                'status'       => 'private',
                'version'      => '0.0.1',
                'tags'         => ['energyops', 'bulk', $key],
                'entities'     => [
                    [
                        'type'        => 'bulk_product',
                        'name'        => 'Bulk product',
                        'description' => 'Energy product line with volume and unit',
                    ],
                ],
                'meta'         => ['extension' => 'energyops'],
                'flow'         => static::deliveryFlow(),
            ]
        );
    }

    public static function deliveryFlow(): array
    {
        $step = function (string $key, string $status, string $details, array $activities, int $sequence, bool $complete = false, ?string $podMethod = null): array {
            return [
                'key'         => $key,
                'code'        => $key,
                'color'       => '#1f2937',
                'logic'       => [],
                'events'      => [],
                'status'      => $status,
                'actions'     => [],
                'details'     => $details,
                'options'     => [],
                'complete'    => $complete,
                'entities'    => [],
                'sequence'    => $sequence,
                'activities'  => $activities,
                'internalId'  => (string) Str::uuid(),
                'pod_method'  => $podMethod ?? 'scan',
                'require_pod' => $podMethod !== null,
            ];
        };

        // Per-pedido POD: a photo of the printed Dynamics invoice / meter when delivering,
        // and the customer's signature to complete the stop.
        return [
            'created'    => $step('created', 'Order Created', 'New energy delivery was created.', ['planned', 'dispatched'], 0),
            'planned'    => $step('planned', 'Planned', 'Load plan packed and vehicle assigned.', ['loaded', 'dispatched'], 1),
            'loaded'     => $step('loaded', 'Loaded', 'Plant confirmed the loaded volumes.', ['enroute', 'dispatched'], 2),
            'enroute'    => $step('enroute', 'Enroute', 'Vehicle is enroute to the customer.', ['delivering'], 3),
            'delivering' => $step('delivering', 'Delivering', 'Meter fill in progress at the site. Photo of the invoice / meter required.', ['completed'], 4, false, 'photo'),
            'completed'  => $step('completed', 'Completed', 'Delivery ticketed and closed. Customer signature required.', [], 5, true, 'signature'),
            'dispatched' => $step('dispatched', 'Dispatched', 'Order was dispatched.', ['enroute', 'loaded'], 6),
        ];
    }

    public static function companyUuid(Company|string|null $company): ?string
    {
        if ($company instanceof Company) {
            return $company->uuid;
        }

        if (is_string($company) && $company !== '') {
            return $company;
        }

        return session('company');
    }
}
