<?php

namespace Fleetbase\EnergyOps\Services;

use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Orchestration\Engines\CompartmentAllocationEngine;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Runs the compartment engine for a set of orders and writes the resulting
 * vehicle / driver assignments back onto the Fleet-Ops orders.
 */
class AllocationService
{
    public function __construct(
        protected CompartmentAllocationEngine $engine,
        protected LoadPlanService $loadPlans
    ) {
    }

    /**
     * Run the compartment engine and persist assignments + load plans.
     *
     * @return array{assignments: array, unassigned: array, summary: array}
     */
    public function allocateAndAssign(Collection $orders, ?Collection $vehicles = null, array $options = []): array
    {
        $orders = $orders->filter(fn ($order) => $order instanceof Order && EnergyOps::isEnergyOrder($order))->values();
        if ($orders->isEmpty()) {
            return ['assignments' => [], 'unassigned' => [], 'summary' => ['skipped' => true, 'reason' => 'no_energy_orders']];
        }

        $companyUuid = $orders->first()->company_uuid;
        $vehicles    = $vehicles ?? Vehicle::query()
            ->where('company_uuid', $companyUuid)
            ->whereHas('energyProfile')
            ->with('driver')
            ->get();

        $result = $this->engine->allocate($orders, $vehicles, $options);

        foreach ($result['assignments'] ?? [] as $assignment) {
            $order = $orders->firstWhere('public_id', $assignment['order_id']);
            if (!$order) {
                continue;
            }

            $vehicle = $vehicles->firstWhere('public_id', $assignment['vehicle_id']);
            if ($vehicle) {
                $order->vehicle_assigned_uuid = $vehicle->uuid;
            }

            if (!empty($assignment['driver_id'])) {
                $driver = Driver::where('company_uuid', $companyUuid)->where('public_id', $assignment['driver_id'])->first();
                if ($driver) {
                    $order->driver_assigned_uuid = $driver->uuid;
                }
            } elseif ($vehicle?->driver) {
                $order->driver_assigned_uuid = $vehicle->driver->uuid;
            }

            if (!empty($assignment['load_plan_uuid'])) {
                $order->setMeta('energyops_load_plan_uuid', $assignment['load_plan_uuid']);
            }
            $order->setMeta('energyops_needs_replan', false);
            $order->setMeta('energyops_trip', $assignment['sequence'] ?? 1);

            if (in_array($order->status, ['created', 'pending'], true)) {
                $order->status = 'planned';
            }

            $order->save();
        }

        $result['load_plans'] = LoadPlan::query()
            ->whereIn('uuid', collect($result['summary']['load_plans'] ?? [])->pluck('uuid')->filter())
            ->with(['vehicle', 'lines.product', 'lines.compartment'])
            ->get()
            ->map(fn (LoadPlan $plan) => (new \Fleetbase\EnergyOps\Http\Resources\v1\LoadPlan($plan))->resolve())
            ->values()
            ->all();

        return $result;
    }
}
