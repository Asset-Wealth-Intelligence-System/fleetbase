<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\EnergyOps\Models\CompatibilityRule;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class CreateCompatibilityRuleRequest extends FleetbaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::can('energy-ops create energy-compatibility-rule') || request()->session()->has('api_credential');
    }

    /**
     * Whether required fields must be present (create) or may be omitted (update).
     */
    protected function requiredRule(): string
    {
        return 'required';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $required = $this->requiredRule();

        return [
            'from_product_uuid'   => ['nullable', 'uuid', Rule::exists('energyops_products', 'uuid')->where('company_uuid', session('company'))],
            'to_product_uuid'     => ['nullable', 'uuid', Rule::exists('energyops_products', 'uuid')->where('company_uuid', session('company'))],
            'from_operation_uuid' => ['nullable', 'uuid', Rule::exists('energyops_operations', 'uuid')->where('company_uuid', session('company'))],
            'to_operation_uuid'   => ['nullable', 'uuid', Rule::exists('energyops_operations', 'uuid')->where('company_uuid', session('company'))],
            'action'              => [$required, Rule::in(CompatibilityRule::ACTIONS)],
            'same_family_only'    => 'nullable|boolean',
            'flush_volume'        => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
            'status'              => ['nullable', Rule::in(['active', 'inactive'])],
            'meta'                => 'nullable|array',
        ];
    }
}
