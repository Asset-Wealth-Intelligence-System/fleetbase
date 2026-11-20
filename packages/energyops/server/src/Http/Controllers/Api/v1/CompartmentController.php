<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Api\v1;

use Fleetbase\EnergyOps\Http\Requests\CreateCompartmentRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateCompartmentRequest;
use Fleetbase\EnergyOps\Http\Resources\v1\Compartment as CompartmentResource;
use Fleetbase\EnergyOps\Models\Compartment;

class CompartmentController extends ApiResourceController
{
    protected string $model         = Compartment::class;
    protected string $resource      = CompartmentResource::class;
    protected ?string $createRequest = CreateCompartmentRequest::class;
    protected ?string $updateRequest = UpdateCompartmentRequest::class;
    protected array $with           = ['dedicatedProduct', 'lastProduct'];
    protected string $label         = 'Compartment';

    protected function publicIdMap(): array
    {
        return [
            'vehicle' => ['vehicle_uuid', \Fleetbase\FleetOps\Models\Vehicle::class],
            'asset' => ['asset_uuid', \Fleetbase\FleetOps\Models\Asset::class],
            'dedicated_product' => ['dedicated_product_uuid', \Fleetbase\EnergyOps\Models\Product::class],
        ];
    }
}
