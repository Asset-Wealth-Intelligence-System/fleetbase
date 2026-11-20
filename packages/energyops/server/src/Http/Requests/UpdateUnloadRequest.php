<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\Support\Auth;

class UpdateUnloadRequest extends CreateUnloadRequest
{
    public function authorize(): bool
    {
        return Auth::can('energy-ops update energy-unload') || request()->session()->has('api_credential');
    }

    protected function requiredRule(): string
    {
        return 'sometimes';
    }
}
