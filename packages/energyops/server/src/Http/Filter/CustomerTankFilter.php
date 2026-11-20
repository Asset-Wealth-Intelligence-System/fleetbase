<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class CustomerTankFilter extends EnergyOpsFilter
{
    public function place(string|array $value): void
    {
        $this->whereRelation('place_uuid', 'place', $value);
    }

    public function product(string|array $value): void
    {
        $this->whereRelation('product_uuid', 'product', $value);
    }

    public function tankType($value): void
    {
        $this->builder->whereIn('tank_type', (array) $value);
    }

    public function placeUuid(string|array $value): void
    {
        $this->builder->whereIn('place_uuid', (array) $value);
    }

    public function productUuid(string|array $value): void
    {
        $this->builder->whereIn('product_uuid', (array) $value);
    }
}
