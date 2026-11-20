<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\EnergyOps\Http\Requests\Concerns\ResolvesUpdatedRecord;
use Fleetbase\EnergyOps\Models\Product;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FleetbaseRequest
{
    use ResolvesUpdatedRecord;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::can('energy-ops create energy-product') || request()->session()->has('api_credential');
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
            'operation_uuid' => [$required, 'uuid', Rule::exists('energyops_operations', 'uuid')->where('company_uuid', session('company'))],
            'code'           => [$required, 'string', 'max:64', $this->uniqueCodeRule()],
            'name'           => [$required, 'string', 'max:191'],
            'description'    => 'nullable|string',
            'density'        => 'nullable|numeric|min:0|max:10',
            'un_number'      => 'nullable|string|max:16',
            'hazmat_class'   => 'nullable|string|max:16',
            'default_unit'   => ['nullable', Rule::in(EnergyOps::UNITS)],
            'skill'          => 'nullable|string|max:64',
            'color'          => 'nullable|string|max:16',
            'active'         => 'nullable|boolean',
            'status'         => ['nullable', Rule::in(['active', 'inactive'])],
            'meta'           => 'nullable|array',
        ];
    }

    /**
     * Custom messages so a duplicate reads as a validation problem rather than a database error.
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'A product with this code already exists.',
        ];
    }

    /**
     * Mirrors the `energyops_products` company-scoped unique index on `code`.
     */
    protected function uniqueCodeRule(): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('energyops_products', 'code')
            ->where(fn ($query) => $query->where('company_uuid', session('company'))->whereNull('deleted_at'));

        return $this->ignoreUpdatedRecord($rule, $this->updatedRecord(Product::class));
    }
}
