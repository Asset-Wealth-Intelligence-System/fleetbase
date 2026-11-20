<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class UnloadLine extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'             => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'        => $this->when(Http::isInternalRequest(), $this->public_id),
            'unload_uuid'      => $this->when(Http::isInternalRequest(), $this->unload_uuid),
            'compartment_uuid' => $this->when(Http::isInternalRequest(), $this->compartment_uuid),
            'product_uuid'     => $this->when(Http::isInternalRequest(), $this->product_uuid),
            'compartment'      => $this->whenLoaded('compartment', fn () => new Compartment($this->compartment)),
            'product'          => $this->whenLoaded('product', fn () => new Product($this->product)),
            'before_volume'    => $this->before_volume,
            'unloaded_volume'  => $this->unloaded_volume,
            'remaining_volume' => $this->remaining_volume,
            'unit'             => $this->unit,
            'sequence'         => (int) $this->sequence,
            'meta'             => data_get($this, 'meta', []),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
