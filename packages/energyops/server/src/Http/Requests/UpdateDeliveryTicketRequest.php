<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\Support\Auth;

class UpdateDeliveryTicketRequest extends CreateDeliveryTicketRequest
{
    public function authorize(): bool
    {
        return Auth::can('energy-ops update energy-delivery-ticket') || request()->session()->has('api_credential');
    }

    protected function requiredRule(): string
    {
        return 'sometimes';
    }
}
