<?php

namespace Fleetbase\EnergyOps\Services;

use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Support\Collection;

class ReloadService
{
    public function __construct(
        protected CompartmentPacker $packer,
        protected LoadPlanService $loadPlans
    ) {
    }

    /**
     * Pack remaining EnergyOps demand onto a vehicle after it returns to plant.
     */
    public function reload(LoadPlan $plan, ?Collection $orders = null): array
    {
        $vehicle = Vehicle::where('uuid', $plan->vehicle_uuid)->with('driver')->firstOrFail();
        $company = $plan->company_uuid;

        $orders = $orders ?? Order::query()
            ->where('company_uuid', $company)
            ->whereIn('status', ['created', 'pending', 'planned', 'dispatched'])
            ->with(['payload.entities', 'payload.dropoff', 'orderConfig'])
            ->get()
            ->filter(function (Order $order) {
                if (!EnergyOps::isEnergyOrder($order)) {
                    return false;
                }
                $remaining = (float) $order->getMeta('energyops_remaining_liters');

                return $remaining > 0.001 || empty($order->vehicle_assigned_uuid);
            })
            ->values();

        if ($orders->isEmpty()) {
            return ['ok' => false, 'reason' => 'no_reload_demand', 'load_plan' => $plan];
        }

        $lines  = $this->packer->demandLines($orders);
        $result = $this->packer->packVehicle($vehicle, $lines, EnergyOps::orderOperation($orders->first()), [
            'allow_partial' => true,
        ]);

        if (!$result['ok'] || empty($result['lines'])) {
            return ['ok' => false, 'reason' => $result['reason'] ?? 'cannot_reload', 'load_plan' => $plan];
        }

        $trip = (int) data_get($plan->meta, 'trip_number', 1) + 1;
        $packs = [
            $vehicle->public_id => [
                'vehicle'     => $vehicle,
                'lines'       => $result['lines'],
                'remaining'   => $result['remaining'],
                'operation'   => EnergyOps::orderOperation($orders->first()),
                'assignments' => $orders->map(fn (Order $order) => [
                    'order_id'   => $order->public_id,
                    'vehicle_id' => $vehicle->public_id,
                    'driver_id'  => $vehicle->driver?->public_id,
                    'sequence'   => $trip,
                    'reload'     => true,
                ])->all(),
            ],
        ];

        $created = $this->loadPlans->persistPacks($packs, $company)->first();
        if ($created) {
            $created->setMeta('trip_number', $trip);
            $created->setMeta('parent_load_plan_uuid', $plan->uuid);
            $created->setMeta('reload', true);
            $created->save();
        }

        foreach ($orders as $order) {
            $taken = collect($result['lines'])->where('order_uuid', $order->uuid)->sum('planned_volume');
            $left  = max(0, (float) $order->getMeta('energyops_remaining_liters') - $taken);
            $order->setMeta('energyops_remaining_liters', $left);
            $order->setMeta('energyops_needs_replan', $left > 0.001);
            $order->vehicle_assigned_uuid = $vehicle->uuid;
            $order->save();
        }

        return [
            'ok'        => true,
            'load_plan' => $created,
            'trip'      => $trip,
            'packed'    => count($result['lines']),
        ];
    }
}
