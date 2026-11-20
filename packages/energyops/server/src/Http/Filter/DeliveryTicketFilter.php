<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class DeliveryTicketFilter extends EnergyOpsFilter
{
    public function orderId(string|array $value): void
    {
        $this->whereRelation('order_uuid', 'order', $value);
    }

    public function loadPlan(string|array $value): void
    {
        $this->whereRelation('load_plan_uuid', 'loadPlan', $value);
    }

    public function compartment(string|array $value): void
    {
        $this->whereRelation('compartment_uuid', 'compartment', $value);
    }

    public function product(string|array $value): void
    {
        $this->whereRelation('product_uuid', 'product', $value);
    }

    public function customerTank(string|array $value): void
    {
        $this->whereRelation('customer_tank_uuid', 'customerTank', $value);
    }

    public function exceptionType($value): void
    {
        $this->builder->whereIn('exception_type', (array) $value);
    }

    public function orderUuid(string|array $value): void
    {
        $this->builder->whereIn('order_uuid', (array) $value);
    }

    public function loadPlanUuid(string|array $value): void
    {
        $this->builder->whereIn('load_plan_uuid', (array) $value);
    }

    public function compartmentUuid(string|array $value): void
    {
        $this->builder->whereIn('compartment_uuid', (array) $value);
    }

    public function productUuid(string|array $value): void
    {
        $this->builder->whereIn('product_uuid', (array) $value);
    }

    public function customerTankUuid(string|array $value): void
    {
        $this->builder->whereIn('customer_tank_uuid', (array) $value);
    }
}
