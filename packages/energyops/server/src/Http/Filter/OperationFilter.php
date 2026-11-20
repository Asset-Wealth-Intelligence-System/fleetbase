<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class OperationFilter extends EnergyOpsFilter
{
    public function family($value): void
    {
        $this->builder->whereIn('family', (array) $value);
    }

    public function unit($value): void
    {
        $this->builder->whereIn('unit', (array) $value);
    }

    public function depot(string|array $value): void
    {
        $this->whereRelation('depot_place_uuid', 'depot', $value);
    }

    public function depotPlaceUuid(string|array $value): void
    {
        $this->builder->whereIn('depot_place_uuid', (array) $value);
    }
}
