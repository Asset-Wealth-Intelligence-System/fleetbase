<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\EnergyOps\Models\Unload;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class CreateUnloadRequest extends FleetbaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::can('energy-ops create energy-unload') || request()->session()->has('api_credential');
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
            'vehicle_uuid'             => [$required, 'string'],
            'driver_uuid'              => ['nullable', 'uuid'],
            'load_plan_uuid'           => ['nullable', 'string'],
            'manifest_uuid'            => ['nullable', 'uuid'],
            'inventory_place_uuid'     => ['nullable', 'string'],
            'inventory_tank_uuid'      => ['nullable', 'uuid', Rule::exists('energyops_customer_tanks', 'uuid')->where('company_uuid', session('company'))],
            'recorded_by_uuid'         => ['nullable', 'uuid'],
            'unit'                     => ['nullable', Rule::in(EnergyOps::UNITS)],
            'source'                   => ['nullable', Rule::in(Unload::SOURCES)],
            'unloaded_at'              => 'nullable|date',
            'notes'                    => 'nullable|string|max:4000',
            'meta'                     => 'nullable|array',
            'confirm'                  => 'nullable|boolean',
            'lines'                    => 'nullable|array',
            'lines.*.uuid'             => 'nullable|uuid',
            'lines.*.compartment_uuid' => 'nullable|string',
            'lines.*.product_uuid'     => 'nullable|uuid',
            'lines.*.unloaded_volume'  => 'nullable|numeric|min:0',
            'lines.*.remaining_volume' => 'nullable|numeric|min:0',
            'lines.*.meta'             => 'nullable|array',
        ];
    }
}
