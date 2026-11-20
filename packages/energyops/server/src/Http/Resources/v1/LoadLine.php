<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class LoadLine extends FleetbaseResource
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
            'load_plan_uuid'   => $this->when(Http::isInternalRequest(), $this->load_plan_uuid),
            'compartment_uuid' => $this->when(Http::isInternalRequest(), $this->compartment_uuid),
            'product_uuid'     => $this->when(Http::isInternalRequest(), $this->product_uuid),
            'order_uuid'       => $this->when(Http::isInternalRequest(), $this->order_uuid),
            'entity_uuid'      => $this->when(Http::isInternalRequest(), $this->entity_uuid),
            'compartment'      => $this->whenLoaded('compartment', fn () => new Compartment($this->compartment)),
            'product'          => $this->whenLoaded('product', fn () => new Product($this->product)),
            'order'            => $this->whenLoaded('order', fn () => new OrderSummary($this->order)),
            'planned_volume'   => $this->planned_volume,
            'loaded_volume'    => $this->loaded_volume,
            'variance'         => $this->variance(),
            'sequence'         => (int) $this->sequence,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
