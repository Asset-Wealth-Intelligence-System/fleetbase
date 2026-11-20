<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\Support\Auth;

class UpdateCompartmentRequest extends CreateCompartmentRequest
{
    public function authorize(): bool
    {
        return Auth::can('energy-ops update energy-compartment') || request()->session()->has('api_credential');
    }

    protected function requiredRule(): string
    {
        return 'sometimes';
    }
}
