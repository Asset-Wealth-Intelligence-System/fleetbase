<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\Support\Auth;

class UpdateCompatibilityRuleRequest extends CreateCompatibilityRuleRequest
{
    public function authorize(): bool
    {
        return Auth::can('energy-ops update energy-compatibility-rule') || request()->session()->has('api_credential');
    }

    protected function requiredRule(): string
    {
        return 'sometimes';
    }
}
