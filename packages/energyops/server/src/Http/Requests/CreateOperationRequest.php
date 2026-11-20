<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\EnergyOps\Http\Requests\Concerns\ResolvesUpdatedRecord;
use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class CreateOperationRequest extends FleetbaseRequest
{
    use ResolvesUpdatedRecord;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::can('energy-ops create energy-operation') || request()->session()->has('api_credential');
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
            'code'                    => [$required, 'string', 'max:32', $this->uniqueCodeRule()],
            'name'                    => [$required, 'string', 'max:191'],
            'family'                  => ['nullable', Rule::in(Operation::FAMILIES)],
            'unit'                    => ['nullable', Rule::in(EnergyOps::UNITS)],
            'depot_place_uuid'        => ['nullable', 'uuid', Rule::exists('places', 'uuid')],
            'allow_split_delivery'    => 'nullable|boolean',
            'block_over_ullage'       => 'nullable|boolean',
            'default_service_seconds' => 'nullable|integer|min:0|max:86400',
            'status'                  => ['nullable', Rule::in(['active', 'inactive'])],
            'meta'                    => 'nullable|array',
        ];
    }

    /**
     * Custom messages so a duplicate reads as a validation problem rather than a database error.
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'An operation with this code already exists.',
        ];
    }

    /**
     * Mirrors the `energyops_operations` company-scoped unique index on `code`.
     */
    protected function uniqueCodeRule(): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('energyops_operations', 'code')
            ->where(fn ($query) => $query->where('company_uuid', session('company'))->whereNull('deleted_at'));

        return $this->ignoreUpdatedRecord($rule, $this->updatedRecord(Operation::class));
    }
}
