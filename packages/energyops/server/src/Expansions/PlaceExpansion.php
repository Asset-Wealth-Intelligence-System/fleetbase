<?php

namespace Fleetbase\EnergyOps\Expansions;

use Fleetbase\Build\Expansion;
use Fleetbase\EnergyOps\Models\CustomerTank;
use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\FleetOps\Models\Place;

/**
 * Adds EnergyOps relationships to Fleet-Ops places (customer sites and depots).
 */
class PlaceExpansion implements Expansion
{
    public static function target()
    {
        return Place::class;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function customerTanks()
    {
        return function () {
            /** @var Place $this */
            return $this->hasMany(CustomerTank::class, 'place_uuid', 'uuid');
        };
    }

    /**
     * Operations using this place as their depot / loading plant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function energyOperations()
    {
        return function () {
            /** @var Place $this */
            return $this->hasMany(Operation::class, 'depot_place_uuid', 'uuid');
        };
    }

    /**
     * Customer tanks at this place with their product loaded.
     */
    public static function loadCustomerTanks()
    {
        return function () {
            /** @var Place $this */
            return $this->customerTanks()->with('product')->get();
        };
    }
}
