<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class UnloadFilter extends EnergyOpsFilter
{
    public function vehicle(string|array $value): void
    {
        $this->whereRelation('vehicle_uuid', 'vehicle', $value);
    }

    public function loadPlan(string|array $value): void
    {
        $this->whereRelation('load_plan_uuid', 'loadPlan', $value);
    }

    public function inventoryPlace(string|array $value): void
    {
        $this->whereRelation('inventory_place_uuid', 'inventoryPlace', $value);
    }

    public function vehicleUuid(string|array $value): void
    {
        $this->builder->whereIn('vehicle_uuid', (array) $value);
    }

    public function driverUuid(string|array $value): void
    {
        $this->builder->whereIn('driver_uuid', (array) $value);
    }

    public function loadPlanUuid(string|array $value): void
    {
        $this->builder->whereIn('load_plan_uuid', (array) $value);
    }

    public function manifestUuid(string|array $value): void
    {
        $this->builder->whereIn('manifest_uuid', (array) $value);
    }

    public function inventoryPlaceUuid(string|array $value): void
    {
        $this->builder->whereIn('inventory_place_uuid', (array) $value);
    }

    public function source(string|array $value): void
    {
        $this->builder->whereIn('source', (array) $value);
    }

    public function unloadedAt($value): void
    {
        $this->applyDateFilter('unloaded_at', $value);
    }
}
