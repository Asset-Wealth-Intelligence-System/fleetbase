<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class VehicleProfileFilter extends EnergyOpsFilter
{
    public function vehicle(string|array $value): void
    {
        $this->whereRelation('vehicle_uuid', 'vehicle', $value);
    }

    public function operation(string|array $value): void
    {
        $this->whereRelation('operation_uuid', 'operation', $value);
    }

    public function coupledAsset(string|array $value): void
    {
        $this->whereRelation('coupled_asset_uuid', 'coupledAsset', $value);
    }

    public function isTractor($value): void
    {
        $this->builder->whereIn('is_tractor', (array) $value);
    }

    public function vehicleUuid(string|array $value): void
    {
        $this->builder->whereIn('vehicle_uuid', (array) $value);
    }

    public function operationUuid(string|array $value): void
    {
        $this->builder->whereIn('operation_uuid', (array) $value);
    }

    public function coupledAssetUuid(string|array $value): void
    {
        $this->builder->whereIn('coupled_asset_uuid', (array) $value);
    }
}
