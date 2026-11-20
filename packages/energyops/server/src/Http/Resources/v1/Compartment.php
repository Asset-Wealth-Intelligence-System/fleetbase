<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class Compartment extends FleetbaseResource
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
            'owner_type'             => $this->owner_type,
            'owner_uuid'             => $this->when(Http::isInternalRequest(), $this->owner_uuid),
            'vehicle_uuid'           => $this->when(Http::isInternalRequest(), $this->vehicle_uuid),
            'asset_uuid'             => $this->when(Http::isInternalRequest(), $this->asset_uuid),
            'owner'                  => $this->owner,
            'dedicated_product_uuid' => $this->when(Http::isInternalRequest(), $this->dedicated_product_uuid),
            'last_product_uuid'      => $this->when(Http::isInternalRequest(), $this->last_product_uuid),
            'dedicated_product'      => $this->whenLoaded('dedicatedProduct', fn () => new Product($this->dedicatedProduct)),
            'last_product'           => $this->whenLoaded('lastProduct', fn () => new Product($this->lastProduct)),
            'code'                   => $this->code,
            'name'                   => $this->name,
            'sequence'               => (int) $this->sequence,
            'capacity_volume'        => $this->capacity_volume,
            'unit'                   => $this->unit,
            'max_fill_percent'       => $this->max_fill_percent,
            'safe_capacity'          => $this->safe_capacity,
            'residual_volume'        => $this->residual_volume,
            'available_volume'       => $this->available_volume,
            'fill_percent'           => $this->fill_percent,
            'requires_flush'         => (bool) $this->requires_flush,
            'status'                 => $this->status,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
