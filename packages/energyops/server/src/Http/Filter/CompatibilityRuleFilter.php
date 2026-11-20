<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class CompatibilityRuleFilter extends EnergyOpsFilter
{
    public function action($value): void
    {
        $this->builder->whereIn('action', (array) $value);
    }

    public function fromProduct(string|array $value): void
    {
        $this->whereRelation('from_product_uuid', 'fromProduct', $value);
    }

    public function toProduct(string|array $value): void
    {
        $this->whereRelation('to_product_uuid', 'toProduct', $value);
    }

    public function fromOperation(string|array $value): void
    {
        $this->whereRelation('from_operation_uuid', 'fromOperation', $value);
    }

    public function toOperation(string|array $value): void
    {
        $this->whereRelation('to_operation_uuid', 'toOperation', $value);
    }

    public function fromProductUuid(string|array $value): void
    {
        $this->builder->whereIn('from_product_uuid', (array) $value);
    }

    public function toProductUuid(string|array $value): void
    {
        $this->builder->whereIn('to_product_uuid', (array) $value);
    }

    public function fromOperationUuid(string|array $value): void
    {
        $this->builder->whereIn('from_operation_uuid', (array) $value);
    }

    public function toOperationUuid(string|array $value): void
    {
        $this->builder->whereIn('to_operation_uuid', (array) $value);
    }
}
