<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class Unload extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        return [
            'id'                     => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'                   => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'              => $this->when(Http::isInternalRequest(), $this->public_id),
            'company_uuid'           => $this->when(Http::isInternalRequest(), $this->company_uuid),
            'created_by_uuid'        => $this->when(Http::isInternalRequest(), $this->created_by_uuid),
            'recorded_by_uuid'       => $this->when(Http::isInternalRequest(), $this->recorded_by_uuid),
            'vehicle_uuid'           => $this->when(Http::isInternalRequest(), $this->vehicle_uuid),
            'driver_uuid'            => $this->when(Http::isInternalRequest(), $this->driver_uuid),
            'load_plan_uuid'         => $this->when(Http::isInternalRequest(), $this->load_plan_uuid),
            'manifest_uuid'          => $this->when(Http::isInternalRequest(), $this->manifest_uuid),
            'inventory_place_uuid'   => $this->when(Http::isInternalRequest(), $this->inventory_place_uuid),
            'inventory_tank_uuid'    => $this->when(Http::isInternalRequest(), $this->inventory_tank_uuid),
            'vehicle'                => $this->whenLoaded('vehicle', fn () => new \Fleetbase\FleetOps\Http\Resources\v1\Vehicle($this->vehicle)),
            'driver'                 => $this->whenLoaded('driver', fn () => $this->driver ? new \Fleetbase\FleetOps\Http\Resources\v1\Driver($this->driver) : null),
            'inventory_place'        => $this->whenLoaded('inventoryPlace', fn () => $this->inventoryPlace ? new \Fleetbase\FleetOps\Http\Resources\v1\Place($this->inventoryPlace) : null),
            'inventory_tank'         => $this->whenLoaded('inventoryTank', fn () => $this->inventoryTank ? new CustomerTank($this->inventoryTank) : null),
            'load_plan'              => $this->loadPlanSummary(),
            'lines'                  => $this->whenLoaded('lines', fn () => UnloadLine::collection($this->lines)),
            'files'                  => $this->whenLoaded('files', fn () => \Fleetbase\Http\Resources\File::collection($this->files)),
            'unit'                   => $this->unit,
            'total_before_volume'    => $this->total_before_volume,
            'total_unloaded_volume'  => $this->total_unloaded_volume,
            'total_remaining_volume' => $this->total_remaining_volume,
            'source'                 => $this->source,
            'status'                 => $this->status,
            'unloaded_at'            => $this->unloaded_at,
            'confirmed_at'           => $this->confirmed_at,
            'voided_at'              => $this->voided_at,
            'notes'                  => $this->notes,
            'meta'                   => data_get($this, 'meta', []),
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }

    /**
     * Compact load plan reference (avoids the plan's heavy default eager loads).
     */
    protected function loadPlanSummary(): ?array
    {
        if (!$this->load_plan_uuid) {
            return null;
        }

        $plan = \Fleetbase\EnergyOps\Models\LoadPlan::query()
            ->without(['vehicle', 'operation', 'depot', 'lines', 'draws', 'tickets', 'files'])
            ->select(['id', 'uuid', 'public_id', 'vehicle_uuid', 'operation_uuid', 'status', 'packed_at', 'loaded_at', 'closed_at'])
            ->where('uuid', $this->load_plan_uuid)
            ->first();

        if (!$plan) {
            return null;
        }

        return [
            'id'        => $this->when(Http::isInternalRequest(), $plan->id, $plan->public_id),
            'uuid'      => $this->when(Http::isInternalRequest(), $plan->uuid),
            'public_id' => $plan->public_id,
            'status'    => $plan->status,
            'packed_at' => $plan->packed_at,
            'loaded_at' => $plan->loaded_at,
            'closed_at' => $plan->closed_at,
        ];
    }
}
