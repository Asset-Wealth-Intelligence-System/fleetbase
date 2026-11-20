<?php

namespace Fleetbase\EnergyOps\Listeners;

use Fleetbase\EnergyOps\Services\AllocationService;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Events\OrderReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleOrderReady implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderReady $event): void
    {
        $order = $event->getModelRecord();
        if (!$order || !EnergyOps::enabled($order->company_uuid) || !EnergyOps::isEnergyOrder($order)) {
            return;
        }

        $settings = EnergyOps::allocationSettings($order->company_uuid);
        if (!($settings['auto_allocate_on_create'] ?? false)) {
            return;
        }

        $order->loadMissing(['payload.entities', 'payload.dropoff', 'payload.pickup', 'payload.waypoints', 'orderConfig']);

        try {
            app(AllocationService::class)->allocateAndAssign(collect([$order]));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
