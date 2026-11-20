<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class CompartmentFilter extends EnergyOpsFilter
{
    public function ownerType($value): void
    {
        $this->builder->whereIn('owner_type', (array) $value);
    }

    public function ownerUuid($value): void
    {
        $this->builder->whereIn('owner_uuid', (array) $value);
    }

    public function vehicle(string|array $value): void
    {
        $this->whereRelation('vehicle_uuid', 'vehicle', $value);
    }

    public function asset(string|array $value): void
    {
        $this->whereRelation('asset_uuid', 'asset', $value);
    }

    public function dedicatedProduct(string|array $value): void
    {
        $this->whereRelation('dedicated_product_uuid', 'dedicatedProduct', $value);
    }

    public function lastProduct(string|array $value): void
    {
        $this->whereRelation('last_product_uuid', 'lastProduct', $value);
    }

    public function vehicleUuid(string|array $value): void
    {
        $this->builder->whereIn('vehicle_uuid', (array) $value);
    }

    public function assetUuid(string|array $value): void
    {
        $this->builder->whereIn('asset_uuid', (array) $value);
    }

    public function dedicatedProductUuid(string|array $value): void
    {
        $this->builder->whereIn('dedicated_product_uuid', (array) $value);
    }

    public function lastProductUuid(string|array $value): void
    {
        $this->builder->whereIn('last_product_uuid', (array) $value);
    }
}
