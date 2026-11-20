<?php

namespace Fleetbase\EnergyOps\Listeners;

use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Services\LoadPlanService;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Events\OrderDispatched;

/**
 * Moves the order's load plan to `in_transit` and links the Fleet-Ops manifest when the order is dispatched.
 */
class HandleOrderDispatched
{
    public function handle(OrderDispatched $event): void
    {
        $order = $event->getModelRecord();
        if (!$order || !EnergyOps::enabled($order->company_uuid) || !EnergyOps::isEnergyOrder($order)) {
            return;
        }

        $plan = EnergyOps::activeLoadPlanForOrder($order);
        if (!$plan) {
            return;
        }

        if ($order->manifest_uuid && !$plan->manifest_uuid) {
            app(LoadPlanService::class)->attachManifest($plan, $order->manifest_uuid);

            return;
        }

        if ($plan->status === LoadPlan::STATUS_LOADED) {
            $plan->status = LoadPlan::STATUS_IN_TRANSIT;
            $plan->save();
        }
    }
}
