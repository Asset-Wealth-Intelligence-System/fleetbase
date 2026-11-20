<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\CustomerTank;
use Fleetbase\EnergyOps\Models\DeliveryTicket;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\EnergyOps\Models\Product;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\OrderConfig;
use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Models\Setting;
use Illuminate\Http\Request;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    /**
     * Current EnergyOps configuration for the session company.
     */
    public function get()
    {
        $company = session('company');

        return response()->json([
            'enabled'                   => EnergyOps::enabled($company),
            'allocation_settings'       => EnergyOps::allocationSettings($company),
            'safety'                    => EnergyOps::safetySettings($company),
            'variance_repack_threshold' => EnergyOps::varianceThreshold($company),
            'units'                     => EnergyOps::UNITS,
            'inventory_place'           => $this->inventoryPlace($company),
            'order_configs'             => $this->orderConfigs($company),
        ]);
    }

    /**
     * Persist EnergyOps configuration for the session company.
     */
    public function save(Request $request)
    {
        if (!Auth::can('energy-ops update setting')) {
            return response()->error('You do not have permission to update Energy Ops settings.', 403);
        }

        $request->validate([
            'enabled'                   => 'nullable|boolean',
            'allocation_engine'         => ['nullable', 'string', Rule::in([EnergyOps::ENGINE_ID, 'vroom', 'default'])],
            'auto_allocate_on_create'   => 'nullable|boolean',
            'safety'                    => 'nullable|array',
            'safety.axle_balance'       => 'nullable|boolean',
            'safety.surge_band'         => 'nullable|boolean',
            'safety.require_load_plan'  => 'nullable|boolean',
            'safety.require_load_proof' => 'nullable|boolean',
            'safety.require_unload_proof' => 'nullable|boolean',
            'inventory_place_uuid'      => 'nullable|string',
            'safety.min_fill_ratio'     => 'nullable|numeric|min:0|max:1',
            'safety.max_fill_ratio'     => 'nullable|numeric|min:0|max:1',
            'variance_repack_threshold' => 'nullable|numeric|min:0|max:1',
        ]);

        $company = session('company');

        if ($request->has('enabled')) {
            EnergyOps::enableForCompany($company, $request->boolean('enabled'));
        }

        if ($request->filled('allocation_engine')) {
            EnergyOps::setAllocationEngine($company, $request->input('allocation_engine'));
        }

        if ($request->has('inventory_place_uuid')) {
            $place = EnergyOps::placeOfType($company, $request->input('inventory_place_uuid'), EnergyOps::PLACE_TYPE_INVENTORY);
            EnergyOps::setInventoryPlace($company, $place?->uuid);
        }

        if ($request->has('auto_allocate_on_create')) {
            $settings                            = EnergyOps::allocationSettings($company);
            $settings['auto_allocate_on_create'] = $request->boolean('auto_allocate_on_create');
            Setting::configure('company.' . $company . '.fleet-ops.allocation-settings', $settings);
        }

        $safety = (array) $request->input('safety', []);
        if ($request->has('variance_repack_threshold')) {
            $safety['variance_repack_threshold'] = (float) $request->input('variance_repack_threshold');
        }
        if (!empty($safety)) {
            EnergyOps::saveSafetySettings($company, $safety);
        }

        return $this->get();
    }

    /**
     * Compact reference to the descarga (inventory) site.
     */
    protected function inventoryPlace(?string $company): ?array
    {
        $place = EnergyOps::inventoryPlace($company);
        if (!$place) {
            return null;
        }

        return [
            'uuid'      => $place->uuid,
            'public_id' => $place->public_id,
            'name'      => $place->name,
            'address'   => $place->address,
        ];
    }

    /**
     * Create the GLP / WOP order configs for the session company if missing.
     */
    public function installOrderConfigs()
    {
        if (!Auth::can('energy-ops update setting')) {
            return response()->error('You do not have permission to update Energy Ops settings.', 403);
        }

        $company = session('company');
        EnergyOps::createOrderConfigs($company);

        return response()->json(['order_configs' => $this->orderConfigs($company)]);
    }

    /**
     * Counters used by the dashboard, settings page and getting-started checklist.
     */
    public function summary()
    {
        $company = session('company');
        $today   = now()->startOfDay();

        $compartments = Compartment::where('company_uuid', $company);

        return response()->json([
            'enabled'          => EnergyOps::enabled($company),
            'operations'       => Operation::where('company_uuid', $company)->count(),
            'products'         => Product::where('company_uuid', $company)->count(),
            'compartments'     => (clone $compartments)->count(),
            'fleet_capacity'   => round((float) (clone $compartments)->get(['capacity_volume', 'unit'])->sum(fn (Compartment $c) => $c->capacityLiters()), 2),
            'vehicle_profiles' => VehicleProfile::where('company_uuid', $company)->count(),
            'customer_tanks'   => CustomerTank::where('company_uuid', $company)->count(),
            'tanks_to_reorder' => CustomerTank::where('company_uuid', $company)->whereNotNull('reorder_point')->whereColumn('last_known_volume', '<=', 'reorder_point')->count(),
            'load_plans_total' => LoadPlan::where('company_uuid', $company)->count(),
            'open_load_plans'  => LoadPlan::where('company_uuid', $company)->whereIn('status', LoadPlan::OPEN_STATUSES)->count(),
            'tickets_total'    => DeliveryTicket::where('company_uuid', $company)->count(),
            'tickets_today'    => DeliveryTicket::where('company_uuid', $company)->where('created_at', '>=', $today)->count(),
            'tickets_30d'      => DeliveryTicket::where('company_uuid', $company)->where('created_at', '>=', now()->subDays(30))->count(),
            'order_configs'    => count($this->orderConfigs($company)),
        ]);
    }

    protected function orderConfigs(?string $company): array
    {
        if (!$company) {
            return [];
        }

        return OrderConfig::query()
            ->where('company_uuid', $company)
            ->where('namespace', 'like', 'energyops:%')
            ->get(['uuid', 'public_id', 'name', 'key', 'namespace', 'status'])
            ->map(fn (OrderConfig $config) => [
                'id'        => $config->public_id,
                'uuid'      => $config->uuid,
                'name'      => $config->name,
                'key'       => $config->key,
                'namespace' => $config->namespace,
                'status'    => $config->status,
            ])
            ->values()
            ->all();
    }
}
