<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class StopDraw extends FleetbaseResource
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
            'load_plan_uuid'     => $this->when(Http::isInternalRequest(), $this->load_plan_uuid),
            'load_line_uuid'     => $this->when(Http::isInternalRequest(), $this->load_line_uuid),
            'order_uuid'         => $this->when(Http::isInternalRequest(), $this->order_uuid),
            'manifest_stop_uuid' => $this->when(Http::isInternalRequest(), $this->manifest_stop_uuid),
            'compartment_uuid'   => $this->when(Http::isInternalRequest(), $this->compartment_uuid),
            'product_uuid'       => $this->when(Http::isInternalRequest(), $this->product_uuid),
            'compartment'        => $this->whenLoaded('compartment', fn () => new Compartment($this->compartment)),
            'product'            => $this->whenLoaded('product', fn () => new Product($this->product)),
            'order'              => $this->whenLoaded('order', fn () => new OrderSummary($this->order)),
            'planned_qty'        => $this->planned_qty,
            'metered_qty'        => $this->metered_qty,
            'sequence'           => (int) $this->sequence,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
