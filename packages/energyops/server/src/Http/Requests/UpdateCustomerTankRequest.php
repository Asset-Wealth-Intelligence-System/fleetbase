<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\Support\Auth;

class UpdateCustomerTankRequest extends CreateCustomerTankRequest
{
    public function authorize(): bool
    {
        return Auth::can('energy-ops update energy-customer-tank') || request()->session()->has('api_credential');
    }

    protected function requiredRule(): string
    {
        return 'sometimes';
    }
}
