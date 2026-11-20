<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

/**
 * Compact representation of a Fleet-Ops asset (trailer) embedded in EnergyOps payloads.
 */
class AssetSummary extends FleetbaseResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'          => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'     => $this->public_id,
            'name'          => $this->name,
            'display_name'  => $this->display_name ?? $this->name,
            'type'          => $this->type,
            'plate_number'  => $this->plate_number,
            'serial_number' => $this->serial_number,
            'status'        => $this->status,
        ];
    }
}
