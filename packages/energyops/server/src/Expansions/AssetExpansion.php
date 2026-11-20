<?php

namespace Fleetbase\EnergyOps\Expansions;

use Fleetbase\Build\Expansion;
use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\FleetOps\Models\Asset;

/**
 * Adds EnergyOps relationships to Fleet-Ops assets (tank trailers).
 */
class AssetExpansion implements Expansion
{
    public static function target()
    {
        return Asset::class;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function compartments()
    {
        return function () {
            /** @var Asset $this */
            return $this->hasMany(Compartment::class, 'asset_uuid', 'uuid')->orderBy('sequence');
        };
    }

    /**
     * Tractor profiles currently coupled to this trailer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function coupledVehicleProfiles()
    {
        return function () {
            /** @var Asset $this */
            return $this->hasMany(VehicleProfile::class, 'coupled_asset_uuid', 'uuid');
        };
    }

    public static function energyCapacity()
    {
        return function () {
            /** @var Asset $this */
            return round((float) $this->compartments()->sum('capacity_volume'), 2);
        };
    }
}
