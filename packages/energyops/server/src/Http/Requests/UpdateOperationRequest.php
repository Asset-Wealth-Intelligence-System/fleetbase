<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\Support\Auth;

class UpdateOperationRequest extends CreateOperationRequest
{
    public function authorize(): bool
    {
        return Auth::can('energy-ops update energy-operation') || request()->session()->has('api_credential');
    }

    protected function requiredRule(): string
    {
        return 'sometimes';
    }
}
