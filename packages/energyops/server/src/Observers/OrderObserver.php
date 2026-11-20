<?php

namespace Fleetbase\EnergyOps\Observers;

use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Tags energy orders with their operation and enforces "no dispatch without a packed load plan".
 */
class OrderObserver
{
    public function creating(Order $order): void
    {
        if (!EnergyOps::isEnergyOrder($order) || !EnergyOps::enabled($order->company_uuid)) {
            return;
        }

        $operation = EnergyOps::orderOperation($order);
        if ($operation) {
            $order->setMeta('energyops_operation', $operation->code);
            $order->setMeta('energyops_operation_uuid', $operation->uuid);
        }

        // Every pedido carries its own POD (photo while delivering, signature to complete)
        $order->pod_required = true;
    }

    public function updating(Order $order): void
    {
        if (!EnergyOps::enabled($order->company_uuid) || !EnergyOps::isEnergyOrder($order)) {
            return;
        }

        $dispatching = $order->isDirty('dispatched') && $order->dispatched;
        $statusGo    = $order->isDirty('status') && in_array($order->status, ['dispatched', 'enroute', 'started'], true);

        if (!$dispatching && !$statusGo) {
            return;
        }

        if (!EnergyOps::requireLoadPlanForDispatch($order->company_uuid)) {
            return;
        }

        $plan = EnergyOps::activeLoadPlanForOrder($order);
        if (!$plan || !in_array($plan->status, ['packed', 'loaded', 'in_transit'], true)) {
            throw ValidationException::withMessages([
                'dispatch' => 'EnergyOps requires a packed load plan before dispatch.',
            ]);
        }
    }
}
