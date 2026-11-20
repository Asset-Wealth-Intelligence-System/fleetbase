<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\EnergyOps\Http\Requests\Concerns\ResolvesUpdatedRecord;
use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class CreateCompartmentRequest extends FleetbaseRequest
{
    use ResolvesUpdatedRecord;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::can('energy-ops create energy-compartment') || request()->session()->has('api_credential');
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
            'owner_type'             => [$required, Rule::in(Compartment::OWNER_TYPES)],
            'owner_uuid'             => [$required, 'uuid'],
            'code'                   => [$required, 'string', 'max:32', $this->uniqueCodeRule()],
            'name'                   => 'nullable|string|max:191',
            'sequence'               => 'nullable|integer|min:1|max:99',
            'capacity_volume'        => [$required, 'numeric', 'min:0'],
            'unit'                   => ['nullable', Rule::in(EnergyOps::UNITS)],
            'max_fill_percent'       => 'nullable|numeric|min:1|max:100',
            'dedicated_product_uuid' => ['nullable', 'uuid', Rule::exists('energyops_products', 'uuid')->where('company_uuid', session('company'))],
            'last_product_uuid'      => ['nullable', 'uuid', Rule::exists('energyops_products', 'uuid')->where('company_uuid', session('company'))],
            'residual_volume'        => 'nullable|numeric|min:0',
            'requires_flush'         => 'nullable|boolean',
            'status'                 => ['nullable', Rule::in(['active', 'inactive'])],
            'meta'                   => 'nullable|array',
        ];
    }

    /**
     * Custom messages so a duplicate code reads as a validation problem rather than a database error.
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'A compartment with this code already exists on this vehicle or trailer.',
        ];
    }

    /**
     * Mirrors the `energyops_compartments_owner_type_owner_uuid_code_unique` index so a duplicate code is
     * reported as a validation error instead of an integrity constraint violation.
     *
     * On update the owner columns may be omitted from the body, so they fall back to the stored record.
     */
    protected function uniqueCodeRule(): \Illuminate\Validation\Rules\Unique
    {
        $existing  = $this->updatedRecord(Compartment::class);
        $ownerType = $this->payloadValue('compartment', 'owner_type') ?? $existing?->owner_type;
        $ownerUuid = $this->payloadValue('compartment', 'owner_uuid')
            ?? $this->payloadValue('compartment', 'vehicle_uuid')
            ?? $this->payloadValue('compartment', 'asset_uuid')
            ?? $existing?->owner_uuid;

        $rule = Rule::unique('energyops_compartments', 'code')
            ->where(function ($query) use ($ownerType, $ownerUuid) {
                $query->where('owner_type', $ownerType);
                $query->where('owner_uuid', $ownerUuid);

                return $query->whereNull('deleted_at');
            });

        return $this->ignoreUpdatedRecord($rule, $existing);
    }
}
