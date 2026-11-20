<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class UnloadLineFilter extends EnergyOpsFilter
{
    public function unloadUuid(string|array $value): void
    {
        $this->builder->whereIn('unload_uuid', (array) $value);
    }

    public function compartmentUuid(string|array $value): void
    {
        $this->builder->whereIn('compartment_uuid', (array) $value);
    }

    public function productUuid(string|array $value): void
    {
        $this->builder->whereIn('product_uuid', (array) $value);
    }
}
