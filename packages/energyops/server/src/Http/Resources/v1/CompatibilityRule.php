<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class CompatibilityRule extends FleetbaseResource
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
            'from_product_uuid'   => $this->when(Http::isInternalRequest(), $this->from_product_uuid),
            'to_product_uuid'     => $this->when(Http::isInternalRequest(), $this->to_product_uuid),
            'from_operation_uuid' => $this->when(Http::isInternalRequest(), $this->from_operation_uuid),
            'to_operation_uuid'   => $this->when(Http::isInternalRequest(), $this->to_operation_uuid),
            'from_product'        => $this->whenLoaded('fromProduct', fn () => new Product($this->fromProduct)),
            'to_product'          => $this->whenLoaded('toProduct', fn () => new Product($this->toProduct)),
            'from_operation'      => $this->whenLoaded('fromOperation', fn () => new Operation($this->fromOperation)),
            'to_operation'        => $this->whenLoaded('toOperation', fn () => new Operation($this->toOperation)),
            'action'              => $this->action,
            'same_family_only'    => (bool) $this->same_family_only,
            'flush_volume'        => $this->flush_volume,
            'notes'               => $this->notes,
            'status'              => $this->status,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
