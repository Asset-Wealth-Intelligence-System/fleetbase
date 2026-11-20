<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Api\v1;

use Fleetbase\EnergyOps\Http\Requests\CreateCustomerTankRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateCustomerTankRequest;
use Fleetbase\EnergyOps\Http\Resources\v1\CustomerTank as CustomerTankResource;
use Fleetbase\EnergyOps\Models\CustomerTank;

class CustomerTankController extends ApiResourceController
{
    protected string $model         = CustomerTank::class;
    protected string $resource      = CustomerTankResource::class;
    protected ?string $createRequest = CreateCustomerTankRequest::class;
    protected ?string $updateRequest = UpdateCustomerTankRequest::class;
    protected array $with           = ['place', 'product'];
    protected string $label         = 'Customer tank';

    protected function publicIdMap(): array
    {
        return [
            'place' => ['place_uuid', \Fleetbase\FleetOps\Models\Place::class],
            'product' => ['product_uuid', \Fleetbase\EnergyOps\Models\Product::class],
        ];
    }
}
