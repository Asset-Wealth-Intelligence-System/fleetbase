<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class ProductFilter extends EnergyOpsFilter
{
    public function operation(string|array $value): void
    {
        $this->whereRelation('operation_uuid', 'operation', $value);
    }

    public function hazmatClass($value): void
    {
        $this->builder->whereIn('hazmat_class', (array) $value);
    }

    public function active($value): void
    {
        $this->builder->whereIn('active', (array) $value);
    }

    public function operationUuid(string|array $value): void
    {
        $this->builder->whereIn('operation_uuid', (array) $value);
    }

    /**
     * Products of a family (`glp` | `wop`) through their operation (or `meta.family` for orphan products).
     */
    public function family(string|array $value): void
    {
        $families = (array) $value;
        $this->builder->where(function ($query) use ($families) {
            $query->whereHas('operation', fn ($q) => $q->whereIn('family', $families));
            foreach ($families as $family) {
                $query->orWhere('meta->family', $family);
            }
        });
    }
}
