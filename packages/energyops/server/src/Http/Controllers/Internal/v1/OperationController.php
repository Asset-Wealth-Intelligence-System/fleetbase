<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Requests\CreateOperationRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateOperationRequest;

class OperationController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'operation';

    /**
     * The form request used to validate creation.
     */
    public $createRequest = CreateOperationRequest::class;

    /**
     * The form request used to validate updates.
     */
    public $updateRequest = UpdateOperationRequest::class;
}
