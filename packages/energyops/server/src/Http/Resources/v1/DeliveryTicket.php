<?php

namespace Fleetbase\EnergyOps\Http\Resources\v1;

use Fleetbase\EnergyOps\Models\LoadPlan as LoadPlanModel;
use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Support\Http;

class DeliveryTicket extends FleetbaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'            => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'       => $this->when(Http::isInternalRequest(), $this->public_id),
            'company_uuid'    => $this->when(Http::isInternalRequest(), $this->company_uuid),
            'created_by_uuid' => $this->when(Http::isInternalRequest(), $this->created_by_uuid),
            'order_uuid'         => $this->when(Http::isInternalRequest(), $this->order_uuid),
            'load_plan_uuid'     => $this->when(Http::isInternalRequest(), $this->load_plan_uuid),
            'stop_draw_uuid'     => $this->when(Http::isInternalRequest(), $this->stop_draw_uuid),
            'compartment_uuid'   => $this->when(Http::isInternalRequest(), $this->compartment_uuid),
            'product_uuid'       => $this->when(Http::isInternalRequest(), $this->product_uuid),
            'customer_tank_uuid' => $this->when(Http::isInternalRequest(), $this->customer_tank_uuid),
            'order'              => $this->whenLoaded('order', fn () => new OrderSummary($this->order)),
            'load_plan'          => $this->loadPlanSummary(),
            'stop_draw'          => $this->whenLoaded('stopDraw', fn () => new StopDraw($this->stopDraw)),
            'compartment'        => $this->whenLoaded('compartment', fn () => new Compartment($this->compartment)),
            'product'            => $this->whenLoaded('product', fn () => new Product($this->product)),
            'customer_tank'      => $this->whenLoaded('customerTank', fn () => new CustomerTank($this->customerTank)),
            'proofs'             => $this->when($this->relationLoaded('order') && $this->order && $this->order->relationLoaded('proofs'), fn () => \Fleetbase\FleetOps\Http\Resources\v1\Proof::collection($this->order->proofs)),
            'metered_qty'        => $this->metered_qty,
            'planned_qty'        => $this->planned_qty,
            'variance_qty'       => $this->variance_qty,
            'variance_percent'   => $this->variancePercent(),
            'variance_reason'    => $this->variance_reason,
            'unit'               => $this->unit,
            'seal_in'            => $this->seal_in,
            'seal_out'           => $this->seal_out,
            'invoice_number'     => $this->invoice_number,
            'meter_start'        => $this->meter_start,
            'meter_end'          => $this->meter_end,
            'exception_type'     => $this->exception_type,
            'status'             => $this->status,
            'issued_at'          => $this->issued_at,
            'meta'            => data_get($this, 'meta', []),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }

    /**
     * Compact load plan reference. The full `LoadPlan` eager loads its tickets (which reference
     * the plan again), so it is never added to `DeliveryTicket::$with`; a trimmed lookup is used instead.
     */
    protected function loadPlanSummary(): ?array
    {
        if ($this->relationLoaded('loadPlan') && $this->loadPlan) {
            return (new LoadPlan($this->loadPlan->withoutRelations()))->resolve();
        }

        if (!$this->load_plan_uuid) {
            return null;
        }

        $plan = LoadPlanModel::query()
            ->without(['vehicle', 'operation', 'depot', 'lines', 'draws', 'tickets', 'files'])
            ->select(['id', 'uuid', 'public_id', 'vehicle_uuid', 'operation_uuid', 'status', 'packed_at', 'loaded_at', 'closed_at'])
            ->where('uuid', $this->load_plan_uuid)
            ->first();

        if (!$plan) {
            return null;
        }

        return [
            'id'             => $this->when(Http::isInternalRequest(), $plan->id, $plan->public_id),
            'uuid'           => $this->when(Http::isInternalRequest(), $plan->uuid),
            'public_id'      => $plan->public_id,
            'vehicle_uuid'   => $this->when(Http::isInternalRequest(), $plan->vehicle_uuid),
            'operation_uuid' => $this->when(Http::isInternalRequest(), $plan->operation_uuid),
            'status'         => $plan->status,
            'packed_at'      => $plan->packed_at,
            'loaded_at'      => $plan->loaded_at,
            'closed_at'      => $plan->closed_at,
        ];
    }
}
