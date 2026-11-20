<?php

namespace Fleetbase\EnergyOps\Listeners;

use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Services\LoadPlanService;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Events\OrderCompleted;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * When the last order of a load plan completes, close the plan.
 */
class HandleOrderCompleted implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderCompleted $event): void
    {
        $order = $event->getModelRecord();
        if (!$order || !EnergyOps::enabled($order->company_uuid) || !EnergyOps::isEnergyOrder($order)) {
            return;
        }

        $plan = EnergyOps::activeLoadPlanForOrder($order);
        if (!$plan) {
            return;
        }

        $plan->loadMissing('lines');
        $openOrders = $plan->lines->pluck('order_uuid')->unique()->filter(function ($orderUuid) use ($order) {
            if ($orderUuid === $order->uuid) {
                return false;
            }
            $sibling = Order::where('company_uuid', $order->company_uuid)->where('uuid', $orderUuid)->first();

            return $sibling && !in_array($sibling->status, ['completed', 'canceled', 'cancelled'], true);
        });

        foreach ($plan->lines->where('order_uuid', $order->uuid) as $line) {
            $compartment = Compartment::where('uuid', $line->compartment_uuid)->first();
            if ($compartment && $line->product_uuid) {
                $compartment->last_product_uuid = $line->product_uuid;
                $compartment->save();
            }
        }

        $order->setMeta('energyops_needs_replan', false);
        $order->saveQuietly();

        if ($openOrders->isEmpty() && !in_array($plan->status, ['closed', 'canceled'], true)) {
            app(LoadPlanService::class)->close($plan);
        }
    }
}
