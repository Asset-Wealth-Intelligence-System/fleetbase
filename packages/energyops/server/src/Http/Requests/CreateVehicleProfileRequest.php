<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\EnergyOps\Http\Requests\Concerns\ResolvesUpdatedRecord;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class CreateVehicleProfileRequest extends FleetbaseRequest
{
    use ResolvesUpdatedRecord;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::can('energy-ops create energy-vehicle-profile') || request()->session()->has('api_credential');
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
            'vehicle_uuid'       => [$required, 'uuid', Rule::exists('vehicles', 'uuid')->where('company_uuid', session('company')), $this->uniqueVehicleUuidRule()],
            'operation_uuid'     => ['nullable', 'uuid', Rule::exists('energyops_operations', 'uuid')->where('company_uuid', session('company'))],
            'coupled_asset_uuid' => ['nullable', 'uuid', Rule::exists('assets', 'uuid')->where('company_uuid', session('company'))],
            'default_depot_place_uuid' => ['nullable', 'uuid', Rule::exists('places', 'uuid')->where('company_uuid', session('company'))],
            'is_tractor'         => 'nullable|boolean',
            'max_payload_kg'     => 'nullable|numeric|min:0',
            'pump_rate_lpm'      => 'nullable|numeric|min:0',
            'has_meter'          => 'nullable|boolean',
            'meter_serial'       => 'nullable|string|max:64',
            'status'             => ['nullable', Rule::in(['active', 'inactive'])],
            'meta'               => 'nullable|array',
        ];
    }

    /**
     * Custom messages so a duplicate reads as a validation problem rather than a database error.
     */
    public function messages(): array
    {
        return [
            'vehicle_uuid.unique' => 'This vehicle already has an energy profile.',
        ];
    }

    /**
     * Mirrors the `energyops_vehicle_profiles` company-scoped unique index on `vehicle_uuid`.
     */
    protected function uniqueVehicleUuidRule(): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('energyops_vehicle_profiles', 'vehicle_uuid')
            ->where(fn ($query) => $query->where('company_uuid', session('company'))->whereNull('deleted_at'));

        return $this->ignoreUpdatedRecord($rule, $this->updatedRecord(VehicleProfile::class));
    }
}
