<?php

namespace Fleetbase\EnergyOps\Services;

use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\CustomerTank;
use Fleetbase\EnergyOps\Models\DeliveryTicket;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Models\StopDraw;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Issues, voids and annotates delivery tickets. Every mutation keeps compartment
 * residuals and customer tank levels consistent with the metered quantities.
 */
class TicketService
{
    /**
     * Issue a ticket for an order stop.
     *
     * Accepts uuids or public ids for order / load plan / stop draw / compartment / product / tank.
     */
    public function issue(array $input): DeliveryTicket
    {
        if (isset($input['delivery_ticket']) && is_array($input['delivery_ticket'])) {
            $input = array_merge($input, $input['delivery_ticket']);
        }

        return DB::transaction(function () use ($input) {
            $companyUuid = $input['company_uuid'] ?? session('company');

            $order = Order::query()
                ->when($companyUuid, fn ($q) => $q->where('company_uuid', $companyUuid))
                ->where(fn ($q) => $q->where('uuid', $input['order_uuid'] ?? '')->orWhere('public_id', $input['order_uuid'] ?? ''))
                ->firstOrFail();

            $plan = !empty($input['load_plan_uuid'])
                ? LoadPlan::where('company_uuid', $order->company_uuid)->where(fn ($q) => $q->where('uuid', $input['load_plan_uuid'])->orWhere('public_id', $input['load_plan_uuid']))->first()
                : EnergyOps::activeLoadPlanForOrder($order);

            $draw = null;
            if (!empty($input['stop_draw_uuid'])) {
                $draw = StopDraw::where('company_uuid', $order->company_uuid)->where(fn ($q) => $q->where('uuid', $input['stop_draw_uuid'])->orWhere('public_id', $input['stop_draw_uuid']))->first();
            } elseif ($plan && !empty($input['compartment_uuid'])) {
                $draw = StopDraw::where('load_plan_uuid', $plan->uuid)
                    ->where('order_uuid', $order->uuid)
                    ->where('compartment_uuid', $input['compartment_uuid'])
                    ->whereDoesntHave('tickets', fn ($q) => $q->where('status', '!=', DeliveryTicket::STATUS_VOID))
                    ->first();
            } elseif ($plan) {
                $draw = StopDraw::where('load_plan_uuid', $plan->uuid)
                    ->where('order_uuid', $order->uuid)
                    ->whereDoesntHave('tickets', fn ($q) => $q->where('status', '!=', DeliveryTicket::STATUS_VOID))
                    ->orderBy('sequence')
                    ->first();
            }

            $compartmentUuid = $input['compartment_uuid'] ?? $draw?->compartment_uuid;
            $productUuid     = $input['product_uuid'] ?? $draw?->product_uuid;
            $planned         = (float) ($input['planned_qty'] ?? $draw?->planned_qty ?? 0);
            $metered         = (float) ($input['metered_qty'] ?? 0);

            if ($metered <= 0 && isset($input['meter_start'], $input['meter_end'])) {
                $metered = max(0, (float) $input['meter_end'] - (float) $input['meter_start']);
            }

            $ticket = DeliveryTicket::create([
                'company_uuid'       => $order->company_uuid,
                'created_by_uuid'    => $input['created_by_uuid'] ?? auth()->id(),
                'order_uuid'         => $order->uuid,
                'load_plan_uuid'     => $plan?->uuid ?? $draw?->load_plan_uuid,
                'stop_draw_uuid'     => $draw?->uuid,
                'compartment_uuid'   => $compartmentUuid,
                'product_uuid'       => $productUuid,
                'customer_tank_uuid' => $input['customer_tank_uuid'] ?? null,
                'metered_qty'        => $metered,
                'planned_qty'        => $planned,
                'variance_qty'       => round($planned - $metered, 3),
                'variance_reason'    => $input['variance_reason'] ?? null,
                'unit'               => $input['unit'] ?? 'L',
                'seal_in'            => $input['seal_in'] ?? null,
                'seal_out'           => $input['seal_out'] ?? null,
                'invoice_number'     => $input['invoice_number'] ?? null,
                'meter_start'        => $input['meter_start'] ?? null,
                'meter_end'          => $input['meter_end'] ?? null,
                'exception_type'     => $input['exception_type'] ?? null,
                'status'             => DeliveryTicket::STATUS_ISSUED,
                'issued_at'          => $input['issued_at'] ?? Carbon::now(),
                'meta'               => $input['meta'] ?? [],
            ]);

            if ($draw) {
                $draw->metered_qty = (float) $draw->metered_qty + $metered;
                $draw->save();
            }

            $this->applyResidual($ticket, $metered);
            $this->applyUllage($ticket, $order, $metered);
            $this->flagVariance($ticket, $order);

            return $ticket->fresh(['order', 'loadPlan', 'stopDraw', 'compartment', 'product', 'customerTank']);
        });
    }

    /**
     * Void a ticket and reverse its effects on the compartment residual and tank level.
     */
    public function void(DeliveryTicket $ticket, ?string $reason = null, ?string $userUuid = null): DeliveryTicket
    {
        return DB::transaction(function () use ($ticket, $reason, $userUuid) {
            if ($ticket->isVoid()) {
                return $ticket;
            }

            $metered = (float) $ticket->metered_qty;

            if ($ticket->stop_draw_uuid) {
                $draw = StopDraw::where('uuid', $ticket->stop_draw_uuid)->first();
                if ($draw) {
                    $draw->metered_qty = max(0, (float) $draw->metered_qty - $metered);
                    $draw->save();
                }
            }

            $this->applyResidual($ticket, -$metered);

            if ($ticket->customer_tank_uuid) {
                $tank = CustomerTank::where('uuid', $ticket->customer_tank_uuid)->first();
                if ($tank) {
                    $tank->last_known_volume = max(0, (float) $tank->last_known_volume - $metered);
                    $tank->save();
                }
            }

            $ticket->status = DeliveryTicket::STATUS_VOID;
            $ticket->setMeta('void_reason', $reason);
            $ticket->setMeta('voided_at', Carbon::now()->toIso8601String());
            $ticket->setMeta('voided_by', $userUuid);
            $ticket->save();

            return $ticket->fresh(['order', 'loadPlan', 'stopDraw', 'compartment', 'product', 'customerTank']);
        });
    }

    /**
     * Annotate a ticket with a delivery exception. Rejects and contamination
     * mark the compartment for flushing and flag the order for re-planning.
     */
    public function recordException(DeliveryTicket $ticket, string $type, ?string $reason = null, ?string $userUuid = null): DeliveryTicket
    {
        return DB::transaction(function () use ($ticket, $type, $reason, $userUuid) {
            $ticket->exception_type = $type;
            if ($reason) {
                $ticket->variance_reason = $reason;
            }
            $ticket->setMeta('exception_recorded_at', Carbon::now()->toIso8601String());
            $ticket->setMeta('exception_recorded_by', $userUuid);
            $ticket->save();

            if (in_array($type, [DeliveryTicket::EXCEPTION_REJECT, DeliveryTicket::EXCEPTION_CONTAMINATE], true) && $ticket->compartment_uuid) {
                Compartment::where('uuid', $ticket->compartment_uuid)->update(['requires_flush' => true]);
            }

            $order = $ticket->order ?? Order::where('uuid', $ticket->order_uuid)->first();
            if ($order) {
                $order->setMeta('energyops_needs_replan', true);
                $order->setMeta('energyops_last_exception', $type);
                $order->save();
            }

            return $ticket->fresh(['order', 'loadPlan', 'stopDraw', 'compartment', 'product', 'customerTank']);
        });
    }

    /**
     * Draw `$delta` (positive = delivered) out of the ticket's compartment.
     */
    protected function applyResidual(DeliveryTicket $ticket, float $delta): void
    {
        if (!$ticket->compartment_uuid) {
            return;
        }

        $compartment = Compartment::where('uuid', $ticket->compartment_uuid)->first();
        if (!$compartment) {
            return;
        }

        // ticket volumes are in the ticket unit; compartments keep their native unit (e.g. gal)
        $deltaLiters                  = EnergyOps::toLiters($delta, (string) ($ticket->unit ?: 'L'), $ticket->product);
        $compartment->residual_volume = max(0, round((float) $compartment->residual_volume - $compartment->fromLiters($deltaLiters), 3));
        if ($delta > 0 && $ticket->product_uuid) {
            $compartment->last_product_uuid = $ticket->product_uuid;
        }
        $compartment->save();
    }

    /**
     * Add `$delta` to the customer tank this ticket fills, resolving the tank from the
     * order's dropoff place when it was not given explicitly.
     */
    protected function applyUllage(DeliveryTicket $ticket, Order $order, float $delta): void
    {
        $tank = null;
        if ($ticket->customer_tank_uuid) {
            $tank = CustomerTank::where('uuid', $ticket->customer_tank_uuid)->first();
        }

        if (!$tank) {
            $placeUuid = $order->payload?->dropoff_uuid ?? $order->payload?->dropoff?->uuid ?? $order->getMeta('energyops_customer_tank_place_uuid');
            $tankUuid  = $order->getMeta('energyops_customer_tank_uuid');
            if ($tankUuid) {
                $tank = CustomerTank::where('company_uuid', $order->company_uuid)->where('uuid', $tankUuid)->first();
            } elseif ($placeUuid && $ticket->product_uuid) {
                $tank = CustomerTank::query()
                    ->where('company_uuid', $order->company_uuid)
                    ->where('place_uuid', $placeUuid)
                    ->where('product_uuid', $ticket->product_uuid)
                    ->first();
            }
        }

        if (!$tank) {
            return;
        }

        if ($delta > 0) {
            $tank->receive($delta, $ticket->issued_at ?? Carbon::now());
        } else {
            $tank->last_known_volume = max(0, (float) $tank->last_known_volume + $delta);
            $tank->save();
        }

        if (!$ticket->customer_tank_uuid) {
            $ticket->customer_tank_uuid = $tank->uuid;
            $ticket->save();
        }
    }

    /**
     * Flag the order for re-planning when the variance exceeds the company threshold.
     */
    protected function flagVariance(DeliveryTicket $ticket, Order $order): void
    {
        $planned = (float) $ticket->planned_qty;
        if ($planned <= 0) {
            return;
        }

        $ratio = abs((float) $ticket->variance_qty) / $planned;
        if ($ratio < EnergyOps::varianceThreshold($order->company_uuid)) {
            return;
        }

        $remaining = max(0, (float) $ticket->variance_qty);
        $order->setMeta('energyops_remaining_liters', EnergyOps::toLiters($remaining, (string) $ticket->unit, $ticket->product));
        $order->setMeta('energyops_needs_replan', $remaining > 0.001);
        $order->save();

        if (!$ticket->exception_type && $remaining > 0.001) {
            $ticket->exception_type = DeliveryTicket::EXCEPTION_SHORT_FILL;
            $ticket->save();
        }
    }
}
