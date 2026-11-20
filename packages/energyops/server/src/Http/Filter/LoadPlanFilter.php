<?php

namespace Fleetbase\EnergyOps\Http\Filter;

class LoadPlanFilter extends EnergyOpsFilter
{
    public function vehicle(string|array $value): void
    {
        $this->whereRelation('vehicle_uuid', 'vehicle', $value);
    }

    public function operation(string|array $value): void
    {
        $this->whereRelation('operation_uuid', 'operation', $value);
    }

    public function manifest(string|array $value): void
    {
        $this->whereRelation('manifest_uuid', 'manifest', $value);
    }

    /**
     * Load plans containing lines for an order (uuid or public id).
     */
    public function orderId(string|array $order): void
    {
        $values = (array) $order;
        $this->builder->whereHas('lines', function ($q) use ($values) {
            $q->whereIn('order_uuid', $values)->orWhereHas('order', fn ($o) => $o->whereIn('public_id', $values));
        });
    }

    public function vehicleUuid(string|array $value): void
    {
        $this->builder->whereIn('vehicle_uuid', (array) $value);
    }

    public function operationUuid(string|array $value): void
    {
        $this->builder->whereIn('operation_uuid', (array) $value);
    }

    public function manifestUuid(string|array $value): void
    {
        $this->builder->whereIn('manifest_uuid', (array) $value);
    }
}
