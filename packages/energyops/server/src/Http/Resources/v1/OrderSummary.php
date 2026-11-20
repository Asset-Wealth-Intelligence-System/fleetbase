<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

/**
 * Compact representation of a Fleet-Ops order embedded in EnergyOps payloads.
 */
class OrderSummary extends FleetbaseResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'            => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'       => $this->public_id,
            'internal_id'     => $this->internal_id,
            'status'          => $this->status,
            'type'            => $this->type,
            'scheduled_at'    => $this->scheduled_at,
            'tracking_number' => $this->whenLoaded('trackingNumber', fn () => $this->trackingNumber?->tracking_number),
            'dropoff_name'    => $this->whenLoaded('payload', fn () => data_get($this->payload, 'dropoff.name')),
            'created_at'      => $this->created_at,
        ];
    }
}
