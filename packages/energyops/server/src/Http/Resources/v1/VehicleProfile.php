<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class VehicleProfile extends FleetbaseResource
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
            'vehicle_uuid'       => $this->when(Http::isInternalRequest(), $this->vehicle_uuid),
            'operation_uuid'     => $this->when(Http::isInternalRequest(), $this->operation_uuid),
            'coupled_asset_uuid' => $this->when(Http::isInternalRequest(), $this->coupled_asset_uuid),
            'default_depot_place_uuid' => $this->when(Http::isInternalRequest(), $this->default_depot_place_uuid),
            'default_depot'      => $this->whenLoaded('defaultDepot', fn () => $this->defaultDepot ? new \Fleetbase\FleetOps\Http\Resources\v1\Place($this->defaultDepot) : null),
            'vehicle'            => $this->whenLoaded('vehicle', fn () => new \Fleetbase\FleetOps\Http\Resources\v1\Vehicle($this->vehicle)),
            'operation'          => $this->whenLoaded('operation', fn () => new Operation($this->operation)),
            'coupled_asset'      => $this->whenLoaded('coupledAsset', fn () => new AssetSummary($this->coupledAsset)),
            'is_tractor'         => (bool) $this->is_tractor,
            'max_payload_kg'     => $this->max_payload_kg,
            'pump_rate_lpm'      => $this->pump_rate_lpm,
            'has_meter'          => (bool) $this->has_meter,
            'meter_serial'       => $this->meter_serial,
            'compartments_count' => $this->compartments_count,
            'total_capacity'     => $this->total_capacity,
            'status'             => $this->status,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
