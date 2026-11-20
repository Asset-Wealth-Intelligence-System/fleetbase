<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Requests\CreateProductRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateProductRequest;

class ProductController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'product';

    /**
     * The form request used to validate creation.
     */
    public $createRequest = CreateProductRequest::class;

    /**
     * The form request used to validate updates.
     */
    public $updateRequest = UpdateProductRequest::class;
}
