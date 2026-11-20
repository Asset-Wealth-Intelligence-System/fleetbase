<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Api\v1;

use Fleetbase\EnergyOps\Http\Requests\CreateDeliveryTicketRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateDeliveryTicketRequest;
use Fleetbase\EnergyOps\Http\Resources\v1\DeliveryTicket as DeliveryTicketResource;
use Fleetbase\EnergyOps\Models\DeliveryTicket;

class DeliveryTicketController extends ApiResourceController
{
    protected string $model         = DeliveryTicket::class;
    protected string $resource      = DeliveryTicketResource::class;
    protected ?string $createRequest = CreateDeliveryTicketRequest::class;
    protected ?string $updateRequest = UpdateDeliveryTicketRequest::class;
    protected array $with           = ['order', 'compartment', 'product', 'customerTank'];
    protected string $label         = 'Delivery ticket';

    protected function publicIdMap(): array
    {
        return [
            'order' => ['order_uuid', \Fleetbase\FleetOps\Models\Order::class],
            'load_plan' => ['load_plan_uuid', \Fleetbase\EnergyOps\Models\LoadPlan::class],
            'compartment' => ['compartment_uuid', \Fleetbase\EnergyOps\Models\Compartment::class],
            'product' => ['product_uuid', \Fleetbase\EnergyOps\Models\Product::class],
            'customer_tank' => ['customer_tank_uuid', \Fleetbase\EnergyOps\Models\CustomerTank::class],
        ];
    }
}
