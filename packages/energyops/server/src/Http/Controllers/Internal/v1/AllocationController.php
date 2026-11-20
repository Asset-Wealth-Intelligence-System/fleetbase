<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Services\AllocationService;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Fleetbase\Support\Auth;

class AllocationController extends Controller
{
    /**
     * Pack a set of energy orders onto vehicles and persist the resulting load plans.
     *
     * Payload: { order_ids: [], vehicle_ids: [], options: {} }
     */
    public function allocate(Request $request)
    {
        if (!Auth::can('energy-ops create load-plan')) {
            return response()->error('You do not have permission to create load plans.', 403);
        }

        $request->validate([
            'order_ids'   => 'nullable|array',
            'vehicle_ids' => 'nullable|array',
            'options'     => 'nullable|array',
        ]);

        $companyUuid = session('company');
        $orderIds    = (array) $request->input('order_ids', []);
        $vehicleIds  = (array) $request->input('vehicle_ids', []);

        $orders = Order::query()
            ->where('company_uuid', $companyUuid)
            ->whereNull('deleted_at')
            ->when($orderIds, fn ($q) => $q->where(fn ($q) => $q->whereIn('public_id', $orderIds)->orWhereIn('uuid', $orderIds)))
            ->when(!$orderIds, fn ($q) => $q->whereIn('status', ['created', 'pending', 'planned']))
            ->with(['payload.entities', 'payload.dropoff', 'payload.pickup', 'payload.waypoints', 'orderConfig'])
            ->get();

        if ($orders->isEmpty()) {
            return response()->error('No orders found to allocate.');
        }

        $vehicles = Vehicle::query()
            ->where('company_uuid', $companyUuid)
            ->when($vehicleIds, fn ($q) => $q->where(fn ($q) => $q->whereIn('public_id', $vehicleIds)->orWhereIn('uuid', $vehicleIds)))
            ->when(!$vehicleIds, fn ($q) => $q->whereHas('energyProfile'))
            ->with('driver')
            ->get();

        if ($vehicles->isEmpty()) {
            return response()->error('No vehicles with an Energy Ops profile were found.');
        }

        $options   = (array) $request->input('options', []);
        $allocator = app(AllocationService::class);

        // When the dispatcher already assigned vehicles on the orders, honor them first and only
        // fall back to the whole profiled fleet for orders those vehicles cannot carry.
        $preferred = $vehicleIds ? collect() : $vehicles->whereIn('uuid', $orders->pluck('vehicle_assigned_uuid')->filter()->unique())->values();
        if ($preferred->isNotEmpty()) {
            $result = $allocator->allocateAndAssign($orders, $preferred, $options);
            $leftover = $orders->whereIn('public_id', (array) ($result['unassigned'] ?? []))->values();
            if ($leftover->isNotEmpty()) {
                $fallback               = $allocator->allocateAndAssign($leftover, $vehicles, $options);
                $result['assignments']  = array_merge((array) ($result['assignments'] ?? []), (array) ($fallback['assignments'] ?? []));
                $result['load_plans']   = array_merge((array) ($result['load_plans'] ?? []), (array) ($fallback['load_plans'] ?? []));
                $result['unassigned']   = (array) ($fallback['unassigned'] ?? []);
                $result['summary']      = array_merge((array) ($result['summary'] ?? []), ['fallback' => $fallback['summary'] ?? []]);
            }

            return response()->json($result);
        }

        $result = $allocator->allocateAndAssign($orders, $vehicles, $options);

        return response()->json($result);
    }
}
