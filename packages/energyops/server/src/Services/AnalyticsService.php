<?php

namespace Fleetbase\EnergyOps\Services;

use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\DeliveryTicket;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Illuminate\Support\Carbon;

class AnalyticsService
{
    /**
     * Variance and exception statistics for issued tickets.
     *
     * @param array{days?:int, operation?:string|null, product?:string|null} $options
     */
    public function variance(string $companyUuid, array $options = []): array
    {
        $days  = (int) ($options['days'] ?? 30);
        $since = Carbon::now()->subDays(max(1, $days));

        $tickets = DeliveryTicket::query()
            ->where('company_uuid', $companyUuid)
            ->where('status', '!=', DeliveryTicket::STATUS_VOID)
            ->where('created_at', '>=', $since)
            ->when(!empty($options['product']), function ($q) use ($options) {
                $q->whereHas('product', fn ($p) => $p->where('uuid', $options['product'])->orWhere('public_id', $options['product'])->orWhere('code', $options['product']));
            })
            ->when(!empty($options['operation']), function ($q) use ($options) {
                $q->whereHas('product.operation', fn ($o) => $o->where('uuid', $options['operation'])->orWhere('code', $options['operation']));
            })
            ->with('product:uuid,code,name')
            ->latest()
            ->limit(2000)
            ->get();

        $threshold = EnergyOps::varianceThreshold($companyUuid);
        $over      = $tickets->filter(function (DeliveryTicket $ticket) use ($threshold) {
            $planned = (float) $ticket->planned_qty;
            if ($planned <= 0) {
                return abs((float) $ticket->variance_qty) > 0;
            }

            return abs((float) $ticket->variance_qty) / $planned >= $threshold;
        });

        $byProduct = $tickets->groupBy('product_uuid')->map(function ($group) {
            $planned = (float) $group->sum('planned_qty');
            $metered = (float) $group->sum('metered_qty');

            return [
                'product_uuid'  => $group->first()?->product_uuid,
                'product'       => $group->first()?->product?->code,
                'product_name'  => $group->first()?->product?->name ?? $group->first()?->product?->code ?? '—',
                'tickets'       => $group->count(),
                'planned'       => round($planned, 2),
                'metered'       => round($metered, 2),
                'variance'      => round($metered - $planned, 2),
                'short_fills'   => $group->where('exception_type', DeliveryTicket::EXCEPTION_SHORT_FILL)->count(),
                'rejects'       => $group->where('exception_type', DeliveryTicket::EXCEPTION_REJECT)->count(),
                'contamination' => $group->where('exception_type', DeliveryTicket::EXCEPTION_CONTAMINATE)->count(),
            ];
        })->values();

        $exceptions   = $tickets->filter(fn (DeliveryTicket $t) => $t->hasException());
        $totalPlanned = (float) $tickets->sum('planned_qty');
        $totalMetered = (float) $tickets->sum('metered_qty');
        $withPlanned  = $tickets->filter(fn (DeliveryTicket $t) => (float) $t->planned_qty > 0);
        $avgPercent   = $withPlanned->isEmpty()
            ? 0
            : $withPlanned->avg(fn (DeliveryTicket $t) => abs((float) $t->variance_qty) / (float) $t->planned_qty * 100);

        return [
            'days'                 => $days,
            'tickets'              => $tickets->count(),
            'threshold'            => $threshold,
            'over_threshold'       => $over->count(),
            'avg_variance_qty'     => round((float) ($tickets->avg('variance_qty') ?? 0), 2),
            'avg_variance_percent' => round((float) $avgPercent, 1),
            'total_planned'        => round($totalPlanned, 2),
            'total_metered'        => round($totalMetered, 2),
            'total_variance'       => round($totalMetered - $totalPlanned, 2),
            'by_product'           => $byProduct,
            'exceptions'           => $exceptions->count(),
            'exceptions_by_type'   => [
                DeliveryTicket::EXCEPTION_SHORT_FILL  => $exceptions->where('exception_type', DeliveryTicket::EXCEPTION_SHORT_FILL)->count(),
                DeliveryTicket::EXCEPTION_REJECT      => $exceptions->where('exception_type', DeliveryTicket::EXCEPTION_REJECT)->count(),
                DeliveryTicket::EXCEPTION_CONTAMINATE => $exceptions->where('exception_type', DeliveryTicket::EXCEPTION_CONTAMINATE)->count(),
            ],
            'recent_over' => $over->take(10)->map(fn (DeliveryTicket $t) => [
                'id'               => $t->public_id,
                'public_id'        => $t->public_id,
                'product'          => $t->product?->code,
                'planned_qty'      => $t->planned_qty,
                'metered_qty'      => $t->metered_qty,
                'variance_qty'     => $t->variance_qty,
                'variance_percent' => $t->variancePercent(),
                'exception_type'   => $t->exception_type,
                'created_at'       => $t->created_at,
            ])->values(),
        ];
    }

    /**
     * Compartment capacity utilisation per vehicle over recent load plans.
     */
    public function utilization(string $companyUuid, int $days = 30): array
    {
        $since = Carbon::now()->subDays(max(1, $days));

        $plans = LoadPlan::query()
            ->where('company_uuid', $companyUuid)
            ->where('created_at', '>=', $since)
            ->where('status', '!=', LoadPlan::STATUS_CANCELED)
            ->with(['vehicle', 'lines:uuid,load_plan_uuid,compartment_uuid,planned_volume,loaded_volume'])
            ->get();

        $capacityByOwner = Compartment::query()
            ->where('company_uuid', $companyUuid)
            ->get(['uuid', 'owner_uuid', 'capacity_volume', 'unit'])
            ->groupBy('owner_uuid')
            ->map(fn ($group) => (object) [
                'capacity'     => (float) $group->sum(fn (Compartment $c) => $c->capacityLiters()),
                'compartments' => $group->count(),
            ]);

        $byVehicle = $plans->groupBy('vehicle_uuid')->map(function ($group, $vehicleUuid) use ($capacityByOwner) {
            $capacity = (float) ($capacityByOwner->get($vehicleUuid)?->capacity ?? 0);
            $planned  = (float) $group->sum(fn (LoadPlan $p) => $p->lines->sum('planned_volume'));
            $loaded   = (float) $group->sum(fn (LoadPlan $p) => $p->lines->sum('loaded_volume'));
            $trips    = $group->count();
            $vehicle  = $group->first()?->vehicle;

            return [
                'vehicle_uuid'      => $vehicleUuid,
                'vehicle'           => $vehicle?->public_id,
                'vehicle_name'      => $vehicle?->display_name ?? $vehicle?->plate_number,
                'trips'             => $trips,
                'capacity'          => round($capacity, 2),
                'planned'           => round($planned, 2),
                'loaded'            => round($loaded, 2),
                'avg_fill_percent'  => $capacity > 0 && $trips > 0 ? round($loaded / ($capacity * $trips) * 100, 1) : null,
            ];
        })->values();

        return [
            'days'       => $days,
            'load_plans' => $plans->count(),
            'by_vehicle' => $byVehicle,
        ];
    }
}
