<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\Support\Auth;

class UpdateProductRequest extends CreateProductRequest
{
    public function authorize(): bool
    {
        return Auth::can('energy-ops update energy-product') || request()->session()->has('api_credential');
    }

    protected function requiredRule(): string
    {
        return 'sometimes';
    }
}
