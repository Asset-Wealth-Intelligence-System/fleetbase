<?php

namespace Fleetbase\EnergyOps\Expansions;

use Fleetbase\Build\Expansion;
use Fleetbase\EnergyOps\Models\DeliveryTicket;
use Fleetbase\EnergyOps\Models\LoadLine;
use Fleetbase\EnergyOps\Models\StopDraw;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Order;

/**
 * Adds EnergyOps relationships and helpers to Fleet-Ops orders.
 */
class OrderExpansion implements Expansion
{
    public static function target()
    {
        return Order::class;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function loadLines()
    {
        return function () {
            /** @var Order $this */
            return $this->hasMany(LoadLine::class, 'order_uuid', 'uuid');
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function stopDraws()
    {
        return function () {
            /** @var Order $this */
            return $this->hasMany(StopDraw::class, 'order_uuid', 'uuid');
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function deliveryTickets()
    {
        return function () {
            /** @var Order $this */
            return $this->hasMany(DeliveryTicket::class, 'order_uuid', 'uuid');
        };
    }

    public static function energyOperation()
    {
        return function () {
            /** @var Order $this */
            return EnergyOps::orderOperation($this);
        };
    }

    public static function energyLoadPlan()
    {
        return function () {
            /** @var Order $this */
            return EnergyOps::activeLoadPlanForOrder($this);
        };
    }

    public static function isEnergyOpsOrder()
    {
        return function () {
            /** @var Order $this */
            return EnergyOps::isEnergyOrder($this);
        };
    }

    /**
     * Total demand of the order in liters, summed over its energy entities.
     */
    public static function energyDemandLiters()
    {
        return function () {
            /** @var Order $this */
            $entities = $this->payload?->entities ?? collect();

            return round((float) collect($entities)->sum(fn ($entity) => (float) $entity->energyVolumeLiters()), 3);
        };
    }
}
