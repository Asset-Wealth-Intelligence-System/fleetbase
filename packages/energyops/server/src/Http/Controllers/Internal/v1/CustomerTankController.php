<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Requests\CreateCustomerTankRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateCustomerTankRequest;

class CustomerTankController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'customer-tank';

    /**
     * The form request used to validate creation.
     */
    public $createRequest = CreateCustomerTankRequest::class;

    /**
     * The form request used to validate updates.
     */
    public $updateRequest = UpdateCustomerTankRequest::class;
}
