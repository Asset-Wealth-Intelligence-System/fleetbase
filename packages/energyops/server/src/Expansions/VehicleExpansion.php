<?php

namespace Fleetbase\EnergyOps\Expansions;

use Fleetbase\Build\Expansion;
use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\FleetOps\Models\Vehicle;

/**
 * Adds EnergyOps relationships to Fleet-Ops vehicles.
 */
class VehicleExpansion implements Expansion
{
    public static function target()
    {
        return Vehicle::class;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public static function energyProfile()
    {
        return function () {
            /** @var Vehicle $this */
            return $this->hasOne(VehicleProfile::class, 'vehicle_uuid', 'uuid');
        };
    }

    /**
     * Compartments mounted directly on this vehicle (rigid tanker).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function compartments()
    {
        return function () {
            /** @var Vehicle $this */
            return $this->hasMany(Compartment::class, 'vehicle_uuid', 'uuid')->orderBy('sequence');
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function loadPlans()
    {
        return function () {
            /** @var Vehicle $this */
            return $this->hasMany(LoadPlan::class, 'vehicle_uuid', 'uuid')->latest();
        };
    }

    /**
     * All compartments usable by this vehicle: its own plus the coupled trailer's when it is a tractor.
     */
    public static function energyCompartments()
    {
        return function () {
            /** @var Vehicle $this */
            $profile    = $this->energyProfile()->first();
            $ownerUuids = [$this->uuid];
            if ($profile?->is_tractor && $profile->coupled_asset_uuid) {
                $ownerUuids[] = $profile->coupled_asset_uuid;
            }

            return Compartment::query()
                ->whereIn('owner_uuid', $ownerUuids)
                ->with(['dedicatedProduct', 'lastProduct'])
                ->orderBy('sequence')
                ->get();
        };
    }

    /**
     * Loaded energy profile with operation and coupled trailer.
     */
    public static function loadEnergyProfile()
    {
        return function () {
            /** @var Vehicle $this */
            return $this->energyProfile()->with(['operation', 'coupledAsset'])->first();
        };
    }

    public static function energyCapacity()
    {
        return function () {
            /** @var Vehicle $this */
            return round((float) $this->energyCompartments()->sum('capacity_volume'), 2);
        };
    }
}
