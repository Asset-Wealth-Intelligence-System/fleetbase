<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Api\v1;

use Fleetbase\EnergyOps\Http\Requests\CreateProductRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateProductRequest;
use Fleetbase\EnergyOps\Http\Resources\v1\Product as ProductResource;
use Fleetbase\EnergyOps\Models\Product;

class ProductController extends ApiResourceController
{
    protected string $model         = Product::class;
    protected string $resource      = ProductResource::class;
    protected ?string $createRequest = CreateProductRequest::class;
    protected ?string $updateRequest = UpdateProductRequest::class;
    protected array $with           = ['operation'];
    protected string $label         = 'Product';

    protected function publicIdMap(): array
    {
        return [
            'operation' => ['operation_uuid', \Fleetbase\EnergyOps\Models\Operation::class],
        ];
    }
}
