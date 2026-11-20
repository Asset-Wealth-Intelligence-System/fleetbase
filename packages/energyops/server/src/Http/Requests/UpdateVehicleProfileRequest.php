<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\Support\Auth;

class UpdateVehicleProfileRequest extends CreateVehicleProfileRequest
{
    public function authorize(): bool
    {
        return Auth::can('energy-ops update energy-vehicle-profile') || request()->session()->has('api_credential');
    }

    protected function requiredRule(): string
    {
        return 'sometimes';
    }
}
