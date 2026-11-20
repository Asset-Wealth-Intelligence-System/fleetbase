<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class Operation extends FleetbaseResource
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
            'depot_place_uuid' => $this->when(Http::isInternalRequest(), $this->depot_place_uuid),
            'depot'            => $this->whenLoaded('depot', fn () => new \Fleetbase\FleetOps\Http\Resources\v1\Place($this->depot)),
            'code'                    => $this->code,
            'name'                    => $this->name,
            'family'                  => $this->family,
            'unit'                    => $this->unit,
            'allow_split_delivery'    => (bool) $this->allow_split_delivery,
            'block_over_ullage'       => (bool) $this->block_over_ullage,
            'default_service_seconds' => (int) $this->default_service_seconds,
            'products_count'          => $this->products_count,
            'products'                => $this->whenLoaded('products', fn () => Product::collection($this->products)),
            'status'                  => $this->status,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
