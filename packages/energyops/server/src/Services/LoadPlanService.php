<?php

namespace Fleetbase\EnergyOps\Services;

use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\DeliveryTicket;
use Fleetbase\EnergyOps\Models\LoadLine;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Models\StopDraw;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\ManifestStop;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists packer output as load plans and drives the plan lifecycle
 * (packed → loaded → in_transit → closed | canceled).
 */
class LoadPlanService
{
    /**
     * Persist packer results. `$packs` is keyed by vehicle public id and each pack holds
     * `vehicle`, `lines`, `remaining`, `operation` and `assignments`.
     */
    public function persistPacks(array $packs, string $companyUuid): Collection
    {
        $plans = collect();

        foreach ($packs as $pack) {
            /** @var Vehicle $vehicle */
            $vehicle = $pack['vehicle'];
            $lines   = $pack['lines'] ?? [];
            if (empty($lines)) {
                continue;
            }

            $plan = DB::transaction(function () use ($vehicle, $pack, $lines, $companyUuid) {
                $this->closeOpenPlans($vehicle->uuid, $companyUuid);

                $operation = $pack['operation'] ?? null;
                $depot     = !empty($pack['depot_place_uuid'])
                    ? EnergyOps::placeOfType($companyUuid, $pack['depot_place_uuid'], EnergyOps::PLACE_TYPE_DEPOT)
                    : EnergyOps::defaultDepotForVehicle($vehicle, $operation);

                $plan = LoadPlan::create([
                    'company_uuid'     => $companyUuid,
                    'created_by_uuid'  => auth()->id(),
                    'vehicle_uuid'     => $vehicle->uuid,
                    'operation_uuid'   => $operation?->uuid ?? null,
                    'depot_place_uuid' => $depot?->uuid,
                    'status'          => LoadPlan::STATUS_PACKED,
                    'packed_at'       => Carbon::now(),
                    'meta'            => [
                        'remaining_volume' => $pack['remaining'] ?? 0,
                        'assignments'      => $pack['assignments'] ?? [],
                    ],
                ]);

                $sequence = 1;
                foreach ($lines as $line) {
                    $loadLine = LoadLine::create([
                        'company_uuid'     => $companyUuid,
                        'load_plan_uuid'   => $plan->uuid,
                        'compartment_uuid' => $line['compartment_uuid'],
                        'product_uuid'     => $line['product_uuid'],
                        'order_uuid'       => $line['order_uuid'],
                        'entity_uuid'      => $line['entity_uuid'] ?? null,
                        'planned_volume'   => $line['planned_volume'],
                        'sequence'         => $sequence++,
                    ]);

                    StopDraw::create([
                        'company_uuid'     => $companyUuid,
                        'load_plan_uuid'   => $plan->uuid,
                        'load_line_uuid'   => $loadLine->uuid,
                        'order_uuid'       => $line['order_uuid'],
                        'compartment_uuid' => $line['compartment_uuid'],
                        'product_uuid'     => $line['product_uuid'],
                        'planned_qty'      => $line['planned_volume'],
                        'sequence'         => $loadLine->sequence,
                    ]);
                }

                $plan->load(['lines', 'draws']);
                $plan->refreshTotals()->save();

                $this->pointPickupsToDepot($plan);

                return $plan;
            });

            $plans->push($plan);
        }

        return $plans;
    }

    /**
     * Plant confirms the actual volume loaded per line; compartments receive the product.
     *
     * `$options` may carry `depot_place_uuid` (override the preset depot with any place of type `depot`)
     * and `proof` (`invoice_number`, `ticket_number`, `notes`) stored under `meta.load_proof`.
     * When the company requires a proof of load the plan must already have at least one attachment.
     */
    public function confirmLoad(LoadPlan $plan, array $actuals = [], array $options = []): LoadPlan
    {
        $this->assertLoadProof($plan, $options);

        return DB::transaction(function () use ($plan, $actuals, $options) {
            $plan->loadMissing(['lines.compartment']);

            if (!empty($options['depot_place_uuid'])) {
                $depot = EnergyOps::placeOfType($plan->company_uuid, $options['depot_place_uuid'], EnergyOps::PLACE_TYPE_DEPOT);
                if (!$depot) {
                    throw ValidationException::withMessages(['depot_place_uuid' => 'The selected loading station is not a depot.']);
                }
                $plan->depot_place_uuid = $depot->uuid;
            }

            $proof = array_filter([
                'invoice_number' => isset($options['proof']['invoice_number']) ? trim((string) $options['proof']['invoice_number']) : null,
                'ticket_number'  => isset($options['proof']['ticket_number']) ? trim((string) $options['proof']['ticket_number']) : null,
                'notes'          => isset($options['proof']['notes']) ? trim((string) $options['proof']['notes']) : null,
            ], fn ($value) => $value !== null && $value !== '');
            if (!empty($proof) || !empty($plan->loadProof())) {
                $plan->setMeta('load_proof', array_merge($plan->loadProof(), $proof, ['confirmed_by_uuid' => auth()->id(), 'confirmed_at' => Carbon::now()->toIso8601String()]));
            }

            // Several pedidos may share one compartment: the compartment receives the sum of its lines.
            $perCompartment = [];
            foreach ($plan->lines as $line) {
                $actual = $actuals[$line->uuid] ?? $actuals[$line->public_id] ?? $line->planned_volume;
                $line->loaded_volume = max(0, (float) $actual);
                $line->save();

                $compartment = $line->compartment ?? Compartment::where('uuid', $line->compartment_uuid)->first();
                if (!$compartment) {
                    continue;
                }

                $perCompartment[$compartment->uuid] ??= ['compartment' => $compartment, 'liters' => 0.0, 'product_uuid' => $line->product_uuid];
                $perCompartment[$compartment->uuid]['liters'] += (float) $line->loaded_volume;
            }

            foreach ($perCompartment as ['compartment' => $compartment, 'liters' => $liters, 'product_uuid' => $productUuid]) {
                // load lines are in liters; compartments keep their native unit (e.g. gal)
                $compartment->residual_volume   = round($compartment->fromLiters($liters), 3);
                $compartment->last_product_uuid = $productUuid;
                $compartment->requires_flush    = false;
                $compartment->save();
            }

            $plan->status    = LoadPlan::STATUS_LOADED;
            $plan->loaded_at = Carbon::now();
            $plan->unsetRelation('lines');
            $plan->refreshTotals()->save();

            $this->syncOrderStatuses($plan, 'loaded');

            return $plan->fresh(['lines', 'draws', 'files', 'depot']);
        });
    }

    /**
     * Link the Fleet-Ops manifest (one vehicle, N pedidos as ordered stops) and align each
     * stop draw with its manifest stop so the draw sequence follows the route.
     */
    public function attachManifest(LoadPlan $plan, ?string $manifestUuid): LoadPlan
    {
        $plan->manifest_uuid = $manifestUuid;
        if (in_array($plan->status, [LoadPlan::STATUS_PACKED, LoadPlan::STATUS_LOADED], true)) {
            $plan->status = LoadPlan::STATUS_IN_TRANSIT;
        }
        $plan->save();

        if ($manifestUuid) {
            $this->syncManifestStops($plan, $manifestUuid);
        }

        return $plan;
    }

    /**
     * Fill `stop_draws.manifest_stop_uuid` / `sequence` from the manifest stops (matched by order).
     */
    public function syncManifestStops(LoadPlan $plan, ?string $manifestUuid = null): void
    {
        $manifestUuid = $manifestUuid ?? $plan->manifest_uuid;
        if (!$manifestUuid) {
            return;
        }

        $stops = ManifestStop::query()
            ->where('manifest_uuid', $manifestUuid)
            ->whereNotNull('order_uuid')
            ->orderBy('sequence')
            ->get()
            ->keyBy('order_uuid');

        if ($stops->isEmpty()) {
            return;
        }

        $draws = StopDraw::query()->where('load_plan_uuid', $plan->uuid)->whereNotNull('order_uuid')->get();
        foreach ($draws as $draw) {
            $stop = $stops->get($draw->order_uuid);
            if (!$stop) {
                continue;
            }

            $draw->manifest_stop_uuid = $stop->uuid;
            $draw->sequence           = (int) $stop->sequence;
            $draw->save();
        }
    }

    /**
     * Late pedidos: add orders to a truck that is already loaded, drawing from the product on board
     * (compartment residual minus what is still planned for undelivered stops). No reload trip is created.
     *
     * @return array{ok: bool, added: array<string>, skipped: array<array{order_id: string, reason: string}>, load_plan: LoadPlan}
     */
    public function addOrders(LoadPlan $plan, Collection $orders, CompartmentPacker $packer): array
    {
        if (!$plan->isOnBoard()) {
            throw ValidationException::withMessages(['load_plan' => 'Only loaded or in-transit load plans can take additional pedidos.']);
        }

        $plan->loadMissing(['lines', 'draws', 'tickets', 'vehicle']);
        $alreadyPlanned = $plan->lines->pluck('order_uuid')->filter()->unique();
        $candidates     = $orders->filter(fn (Order $order) => !$alreadyPlanned->contains($order->uuid))->values();

        $skipped = [];
        foreach ($orders as $order) {
            if ($alreadyPlanned->contains($order->uuid)) {
                $skipped[] = ['order_id' => $order->public_id, 'reason' => 'already_on_plan'];
            }
        }

        if ($candidates->isEmpty()) {
            return ['ok' => false, 'added' => [], 'skipped' => $skipped, 'load_plan' => $plan];
        }

        $demand = $packer->demandLines($candidates)->groupBy('order_uuid');
        $stock  = $this->onBoardStock($plan);
        $added  = [];

        DB::transaction(function () use ($plan, $candidates, $demand, &$stock, &$added, &$skipped) {
            $sequence = (int) ($plan->lines->max('sequence') ?? 0);

            foreach ($candidates as $order) {
                $lines = $demand->get($order->uuid);
                if (!$lines || $lines->isEmpty()) {
                    $skipped[] = ['order_id' => $order->public_id, 'reason' => 'no_energy_lines'];
                    continue;
                }

                $allocation = $this->allocateFromStock($lines, $stock);
                if ($allocation === null) {
                    $skipped[] = ['order_id' => $order->public_id, 'reason' => 'insufficient_on_board_stock'];
                    continue;
                }

                foreach ($allocation as $take) {
                    $stock[$take['compartment_uuid']]['available'] -= $take['liters'];
                    $sequence++;

                    $loadLine = LoadLine::create([
                        'company_uuid'     => $plan->company_uuid,
                        'load_plan_uuid'   => $plan->uuid,
                        'compartment_uuid' => $take['compartment_uuid'],
                        'product_uuid'     => $take['product_uuid'],
                        'order_uuid'       => $order->uuid,
                        'entity_uuid'      => $take['entity_uuid'],
                        'planned_volume'   => round($take['liters'], 3),
                        // product is already on board: the "loaded" volume is what this pedido draws from stock
                        'loaded_volume'    => round($take['liters'], 3),
                        'sequence'         => $sequence,
                        'meta'             => ['from_on_board_stock' => true],
                    ]);

                    StopDraw::create([
                        'company_uuid'     => $plan->company_uuid,
                        'load_plan_uuid'   => $plan->uuid,
                        'load_line_uuid'   => $loadLine->uuid,
                        'order_uuid'       => $order->uuid,
                        'compartment_uuid' => $take['compartment_uuid'],
                        'product_uuid'     => $take['product_uuid'],
                        'planned_qty'      => round($take['liters'], 3),
                        'sequence'         => $sequence,
                    ]);
                }

                $order->vehicle_assigned_uuid = $plan->vehicle_uuid;
                $order->setMeta('energyops_load_plan_uuid', $plan->uuid);
                $order->setMeta('energyops_needs_replan', false);
                $order->setMeta('energyops_added_to_loaded_truck', true);
                if (in_array($order->status, ['created', 'pending'], true)) {
                    $order->status = 'planned';
                }
                $order->save();

                $added[] = $order->public_id;
            }

            $plan->unsetRelation('lines');
            $plan->refreshTotals()->save();
        });

        return ['ok' => !empty($added), 'added' => $added, 'skipped' => $skipped, 'load_plan' => $plan->fresh(['lines', 'draws'])];
    }

    /**
     * Liters still uncommitted per compartment: physical residual minus planned, not yet delivered draws.
     *
     * @return array<string, array{compartment: Compartment, product_uuid: ?string, available: float}>
     */
    public function onBoardStock(LoadPlan $plan): array
    {
        $plan->loadMissing(['lines.compartment', 'draws']);

        $ticketed = DeliveryTicket::query()
            ->where('load_plan_uuid', $plan->uuid)
            ->where('status', '!=', DeliveryTicket::STATUS_VOID)
            ->whereNotNull('stop_draw_uuid')
            ->pluck('stop_draw_uuid')
            ->all();

        $stock = [];
        foreach ($plan->lines as $line) {
            $compartment = $line->compartment ?? Compartment::where('uuid', $line->compartment_uuid)->first();
            if (!$compartment || isset($stock[$compartment->uuid])) {
                continue;
            }

            $stock[$compartment->uuid] = [
                'compartment'  => $compartment,
                'product_uuid' => $compartment->last_product_uuid ?? $line->product_uuid,
                'available'    => $compartment->residualVolumeLiters(),
            ];
        }

        foreach ($plan->draws as $draw) {
            if (!isset($stock[$draw->compartment_uuid]) || in_array($draw->uuid, $ticketed, true)) {
                continue;
            }
            // pending stop: its planned quantity is spoken for
            $stock[$draw->compartment_uuid]['available'] -= max(0, (float) $draw->planned_qty - (float) $draw->metered_qty);
        }

        foreach ($stock as &$cell) {
            $cell['available'] = max(0, round($cell['available'], 3));
        }

        return $stock;
    }

    /**
     * Greedy allocation of one order's demand lines against on-board stock (same product only).
     *
     * @return array<array{compartment_uuid: string, product_uuid: string, entity_uuid: ?string, liters: float}>|null
     */
    protected function allocateFromStock(Collection $lines, array $stock): ?array
    {
        $allocation = [];

        foreach ($lines as $line) {
            $left  = (float) $line['liters'];
            $cells = collect($stock)
                ->filter(fn ($cell) => $cell['available'] > 0.001 && $cell['product_uuid'] === $line['product_uuid'])
                ->sortByDesc('available');

            foreach ($cells as $uuid => $cell) {
                if ($left <= 0.001) {
                    break;
                }
                $take = min($left, $cell['available']);
                $stock[$uuid]['available'] -= $take;
                $left                      -= $take;
                $allocation[]               = [
                    'compartment_uuid' => $uuid,
                    'product_uuid'     => $line['product_uuid'],
                    'entity_uuid'      => $line['entity_uuid'] ?? null,
                    'liters'           => $take,
                ];
            }

            if ($left > 0.001) {
                return null;
            }
        }

        return $allocation;
    }

    /**
     * Reject a confirm-load without attachments when the company requires a comprobante de carga.
     */
    protected function assertLoadProof(LoadPlan $plan, array $options): void
    {
        if (!empty($options['skip_proof_check']) || !EnergyOps::requireLoadProof($plan->company_uuid)) {
            return;
        }

        if ($plan->files()->count() === 0) {
            throw ValidationException::withMessages([
                'proof' => 'Attach the loading receipt (factura / boleta de carga) before confirming the load.',
            ]);
        }
    }

    public function close(LoadPlan $plan): LoadPlan
    {
        $plan->status    = LoadPlan::STATUS_CLOSED;
        $plan->closed_at = Carbon::now();
        $plan->save();

        return $plan;
    }

    /**
     * Cancel a plan that has not been loaded, releasing its orders back to the pool.
     */
    public function cancel(LoadPlan $plan, ?string $reason = null): LoadPlan
    {
        return DB::transaction(function () use ($plan, $reason) {
            $plan->status    = LoadPlan::STATUS_CANCELED;
            $plan->closed_at = Carbon::now();
            $plan->setMeta('cancel_reason', $reason);
            $plan->save();

            $orderUuids = $plan->lines()->pluck('order_uuid')->filter()->unique();
            Order::query()
                ->where('company_uuid', $plan->company_uuid)
                ->whereIn('uuid', $orderUuids)
                ->where('vehicle_assigned_uuid', $plan->vehicle_uuid)
                ->get()
                ->each(function (Order $order) {
                    $order->vehicle_assigned_uuid = null;
                    $order->setMeta('energyops_load_plan_uuid', null);
                    $order->setMeta('energyops_needs_replan', true);
                    $order->save();
                });

            return $plan;
        });
    }

    /**
     * The most recent open plan carrying this order.
     */
    public function forOrder(Order $order): ?LoadPlan
    {
        return LoadPlan::query()
            ->where('company_uuid', $order->company_uuid)
            ->whereHas('lines', fn ($q) => $q->where('order_uuid', $order->uuid))
            ->with(['vehicle', 'operation', 'lines.compartment', 'lines.product', 'draws', 'tickets'])
            ->orderByRaw("CASE WHEN status IN ('draft','packed','loaded','in_transit') THEN 0 ELSE 1 END")
            ->latest()
            ->first();
    }

    /**
     * Move the plan's orders to an activity when the order config knows it.
     */
    protected function syncOrderStatuses(LoadPlan $plan, string $activity): void
    {
        $orderUuids = $plan->lines()->pluck('order_uuid')->filter()->unique();
        if ($orderUuids->isEmpty()) {
            return;
        }

        Order::query()
            ->where('company_uuid', $plan->company_uuid)
            ->whereIn('uuid', $orderUuids)
            ->whereIn('status', ['created', 'planned', 'pending'])
            ->get()
            ->each(function (Order $order) use ($activity, $plan) {
                $order->setMeta('energyops_load_plan_uuid', $plan->uuid);
                $order->status = $activity;
                $order->save();
            });
    }

    /**
     * Orders whose pickup was prefilled automatically follow the depot of the vehicle they were packed on.
     */
    protected function pointPickupsToDepot(LoadPlan $plan): void
    {
        if (!$plan->depot_place_uuid) {
            return;
        }

        $orderUuids = $plan->lines->pluck('order_uuid')->filter()->unique();
        if ($orderUuids->isEmpty()) {
            return;
        }

        Order::query()
            ->where('company_uuid', $plan->company_uuid)
            ->whereIn('uuid', $orderUuids)
            ->with('payload')
            ->get()
            ->each(function (Order $order) use ($plan) {
                $payload = $order->payload;
                if (!$payload) {
                    return;
                }

                $auto = (bool) data_get($payload->meta, 'energyops_pickup_auto', false);
                if (!$auto && $payload->pickup_uuid) {
                    return;
                }

                if ($payload->pickup_uuid === $plan->depot_place_uuid) {
                    return;
                }

                $payload->pickup_uuid = $plan->depot_place_uuid;
                $meta                 = is_array($payload->meta) ? $payload->meta : [];
                $meta['energyops_pickup_auto'] = true;
                $payload->meta                 = $meta;
                $payload->save();
            });
    }

    protected function closeOpenPlans(string $vehicleUuid, string $companyUuid): void
    {
        LoadPlan::query()
            ->where('company_uuid', $companyUuid)
            ->where('vehicle_uuid', $vehicleUuid)
            ->whereIn('status', [LoadPlan::STATUS_DRAFT, LoadPlan::STATUS_PACKED])
            ->update(['status' => LoadPlan::STATUS_CLOSED, 'closed_at' => Carbon::now()]);
    }
}
