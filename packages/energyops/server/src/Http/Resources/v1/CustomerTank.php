<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class CustomerTank extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'            => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'       => $this->when(Http::isInternalRequest(), $this->public_id),
            'company_uuid'    => $this->when(Http::isInternalRequest(), $this->company_uuid),
            'created_by_uuid' => $this->when(Http::isInternalRequest(), $this->created_by_uuid),
            'place_uuid'        => $this->when(Http::isInternalRequest(), $this->place_uuid),
            'product_uuid'      => $this->when(Http::isInternalRequest(), $this->product_uuid),
            'place'             => $this->whenLoaded('place', fn () => new \Fleetbase\FleetOps\Http\Resources\v1\Place($this->place)),
            'product'           => $this->whenLoaded('product', fn () => new Product($this->product)),
            'name'              => $this->name,
            'serial_number'     => $this->serial_number,
            'tank_type'         => $this->tank_type,
            'capacity_volume'   => $this->capacity_volume,
            'unit'              => $this->unit,
            'safe_fill_percent' => $this->safe_fill_percent,
            'safe_fill_volume'  => $this->safe_fill_volume,
            'last_known_volume' => $this->last_known_volume,
            'reorder_point'     => $this->reorder_point,
            'ullage'            => $this->ullage,
            'fill_percent'      => $this->fill_percent,
            'needs_reorder'     => $this->needs_reorder,
            'last_delivery_at'  => $this->last_delivery_at,
            'status'            => $this->status,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
