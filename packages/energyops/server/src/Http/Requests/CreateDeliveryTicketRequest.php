<?php

namespace Fleetbase\EnergyOps\Http\Requests;

use Fleetbase\EnergyOps\Models\DeliveryTicket;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Auth;
use Illuminate\Validation\Rule;

class CreateDeliveryTicketRequest extends FleetbaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::can('energy-ops create energy-delivery-ticket') || request()->session()->has('api_credential');
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
            'order_uuid'         => [$required, 'uuid', Rule::exists('orders', 'uuid')->where('company_uuid', session('company'))],
            'load_plan_uuid'     => ['nullable', 'uuid', Rule::exists('energyops_load_plans', 'uuid')->where('company_uuid', session('company'))],
            'stop_draw_uuid'     => ['nullable', 'uuid', Rule::exists('energyops_stop_draws', 'uuid')->where('company_uuid', session('company'))],
            'compartment_uuid'   => ['nullable', 'uuid', Rule::exists('energyops_compartments', 'uuid')->where('company_uuid', session('company'))],
            'product_uuid'       => ['nullable', 'uuid', Rule::exists('energyops_products', 'uuid')->where('company_uuid', session('company'))],
            'customer_tank_uuid' => ['nullable', 'uuid', Rule::exists('energyops_customer_tanks', 'uuid')->where('company_uuid', session('company'))],
            'metered_qty'        => [$required, 'numeric', 'min:0'],
            'planned_qty'        => 'nullable|numeric|min:0',
            'variance_reason'    => 'nullable|string|max:191',
            'unit'               => ['nullable', Rule::in(EnergyOps::UNITS)],
            'seal_in'            => 'nullable|string|max:64',
            'seal_out'           => 'nullable|string|max:64',
            'invoice_number'     => 'nullable|string|max:64',
            'meter_start'        => 'nullable|string|max:64',
            'meter_end'          => 'nullable|string|max:64',
            'exception_type'     => ['nullable', Rule::in(DeliveryTicket::EXCEPTION_TYPES)],
            'issued_at'          => 'nullable|date',
            'meta'               => 'nullable|array',
        ];
    }
}
