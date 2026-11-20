<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class LoadPlan extends FleetbaseResource
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
            'vehicle_uuid'         => $this->when(Http::isInternalRequest(), $this->vehicle_uuid),
            'operation_uuid'       => $this->when(Http::isInternalRequest(), $this->operation_uuid),
            'manifest_uuid'        => $this->when(Http::isInternalRequest(), $this->manifest_uuid),
            'depot_place_uuid'     => $this->when(Http::isInternalRequest(), $this->depot_place_uuid),
            'vehicle'              => $this->whenLoaded('vehicle', fn () => new \Fleetbase\FleetOps\Http\Resources\v1\Vehicle($this->vehicle)),
            'operation'            => $this->whenLoaded('operation', fn () => new Operation($this->operation)),
            'depot'                => $this->whenLoaded('depot', fn () => $this->depot ? new \Fleetbase\FleetOps\Http\Resources\v1\Place($this->depot) : null),
            'files'                => $this->whenLoaded('files', fn () => \Fleetbase\Http\Resources\File::collection($this->files)),
            'load_proof'           => $this->loadProof(),
            'lines'                => $this->whenLoaded('lines', fn () => LoadLine::collection($this->lines)),
            'draws'                => $this->whenLoaded('draws', fn () => StopDraw::collection($this->draws)),
            'tickets'              => $this->whenLoaded('tickets', fn () => DeliveryTicket::collection($this->tickets)),
            'status'               => $this->status,
            'total_planned_volume' => $this->total_planned_volume,
            'total_loaded_volume'  => $this->total_loaded_volume,
            'orders_count'         => (int) $this->orders_count,
            'packed_at'            => $this->packed_at,
            'loaded_at'            => $this->loaded_at,
            'closed_at'            => $this->closed_at,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
