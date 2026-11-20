<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class Product extends FleetbaseResource
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
            'operation_uuid' => $this->when(Http::isInternalRequest(), $this->operation_uuid),
            'operation'      => $this->whenLoaded('operation', fn () => new Operation($this->operation)),
            'code'           => $this->code,
            'name'           => $this->name,
            'description'    => $this->description,
            'density'        => $this->density,
            'un_number'      => $this->un_number,
            'hazmat_class'   => $this->hazmat_class,
            'default_unit'   => $this->default_unit,
            'skill'          => $this->skill,
            'color'          => $this->color,
            'active'         => (bool) $this->active,
            'status'         => $this->status,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
