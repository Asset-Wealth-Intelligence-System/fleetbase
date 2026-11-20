<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Requests\CreateVehicleProfileRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateVehicleProfileRequest;

class VehicleProfileController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'vehicle-profile';

    /**
     * The form request used to validate creation.
     */
    public $createRequest = CreateVehicleProfileRequest::class;

    /**
     * The form request used to validate updates.
     */
    public $updateRequest = UpdateVehicleProfileRequest::class;
}
