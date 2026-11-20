<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\EnergyOps\Models\CustomerTank;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class CreateCustomerTankRequest extends FleetbaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::can('energy-ops create energy-customer-tank') || request()->session()->has('api_credential');
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
            'place_uuid'        => [$required, 'uuid', Rule::exists('places', 'uuid')->where('company_uuid', session('company'))],
            'product_uuid'      => ['nullable', 'uuid', Rule::exists('energyops_products', 'uuid')->where('company_uuid', session('company'))],
            'name'              => [$required, 'string', 'max:191'],
            'serial_number'     => 'nullable|string|max:64',
            'tank_type'         => ['nullable', Rule::in(CustomerTank::TANK_TYPES)],
            'capacity_volume'   => [$required, 'numeric', 'min:0'],
            'unit'              => ['nullable', Rule::in(EnergyOps::UNITS)],
            'safe_fill_percent' => 'nullable|numeric|min:1|max:100',
            'last_known_volume' => 'nullable|numeric|min:0',
            'reorder_point'     => 'nullable|numeric|min:0',
            'last_delivery_at'  => 'nullable|date',
            'status'            => ['nullable', Rule::in(['active', 'inactive'])],
            'meta'              => 'nullable|array',
        ];
    }
}
