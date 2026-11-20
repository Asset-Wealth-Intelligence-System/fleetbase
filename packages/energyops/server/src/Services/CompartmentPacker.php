<?php

namespace Fleetbase\EnergyOps\Services;

use Fleetbase\EnergyOps\Models\CompatibilityRule;
use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\CustomerTank;
use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\EnergyOps\Models\Product;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CompartmentPacker
{
    /**
     * Build demand lines from EnergyOps orders.
     *
     * @return Collection<int, array>
     */
    public function demandLines(Collection $orders): Collection
    {
        $lines = collect();

        foreach ($orders as $order) {
            $operation = EnergyOps::orderOperation($order);
            $entities  = $order->payload?->entities ?? collect();

            if ($entities->isEmpty()) {
                $lines->push($this->lineFromMeta($order, $operation));
                continue;
            }

            foreach ($entities as $entity) {
                $product = $this->resolveProduct($order, $entity);
                if (!$product) {
                    // not an energy line (e.g. a parcel on a mixed order)
                    continue;
                }

                $qty    = (float) ($entity->getMeta('volume') ?? $entity->getMeta('qty') ?? $entity->weight ?? 0);
                $unit   = (string) ($entity->getMeta('unit') ?? $product->default_unit ?? $operation?->unit ?? 'L');
                $liters = EnergyOps::toLiters($qty, $unit, $product);
                $tank   = $entity->getMeta('energyops_customer_tank_uuid');

                $lines->push([
                    'order'          => $order,
                    'order_uuid'     => $order->uuid,
                    'order_id'       => $order->public_id,
                    'entity'         => $entity,
                    'entity_uuid'    => $entity->uuid,
                    'product'        => $product,
                    'product_uuid'   => $product?->uuid,
                    'operation'      => $operation ?? $product?->operation,
                    'operation_uuid' => $operation?->uuid ?? $product?->operation_uuid,
                    'liters'         => $liters,
                    'unit'           => $unit,
                    'tank_uuid'      => $tank,
                    'ullage_ok'      => $this->ullageAllows($order, $product, $liters, $operation, $tank),
                ]);
            }
        }

        return $lines->filter(fn ($line) => ($line['liters'] ?? 0) > 0 && !empty($line['product_uuid']));
    }

    /**
     * Pack a set of demand lines onto a vehicle's compartments.
     *
     * @return array{ok: bool, lines: array, remaining: float, reason?: string}
     */
    public function packVehicle(Vehicle $vehicle, Collection $demandLines, ?Operation $operation = null, array $options = []): array
    {
        $allowPartial = (bool) ($options['allow_partial'] ?? false);
        $profile      = $this->profileFor($vehicle);
        $vehicleOp    = $profile?->operation;
        $operation    = $operation ?? $demandLines->first()['operation'] ?? $vehicleOp;

        if (!$profile) {
            return ['ok' => false, 'lines' => [], 'remaining' => 0, 'reason' => 'no_profile'];
        }

        if ($vehicleOp && $operation && $vehicleOp->uuid !== $operation->uuid) {
            return ['ok' => false, 'lines' => [], 'remaining' => 0, 'reason' => 'operation_mismatch'];
        }

        $compartments = $this->compartmentsFor($vehicle, $profile);
        if ($compartments->isEmpty()) {
            return ['ok' => false, 'lines' => [], 'remaining' => 0, 'reason' => 'no_compartments'];
        }

        $cells = $compartments->map(function (Compartment $c) {
            return [
                'compartment' => $c,
                'product_uuid'=> $c->residual_volume > 0 ? $c->last_product_uuid : $c->dedicated_product_uuid,
                'remaining'   => $c->availableVolumeLiters(),
                'used'        => 0.0,
            ];
        })->values()->all();

        $packed   = [];
        $skipped  = [];
        $sorted   = $demandLines->sortByDesc('liters')->values();
        $splitOk  = (bool) ($operation?->allow_split_delivery);

        foreach ($sorted as $demand) {
            $left = (float) $demand['liters'];
            if (!$demand['ullage_ok'] && $operation?->block_over_ullage) {
                if ($allowPartial) {
                    $skipped[] = ['order_id' => $demand['order_id'], 'reason' => 'ullage_exceeded'];
                    continue;
                }

                return ['ok' => false, 'lines' => [], 'remaining' => 0, 'reason' => 'ullage_exceeded', 'order_id' => $demand['order_id']];
            }

            $attempt    = [];
            $snapshot   = $cells;
            $candidates = $this->candidateIndexes($cells, $demand['product'], $left);
            foreach ($candidates as $idx) {
                if ($left <= 0.001) {
                    break;
                }
                $take = min($left, $cells[$idx]['remaining']);
                if ($take <= 0) {
                    continue;
                }

                // A demand line may only span several compartments when the operation allows split deliveries
                if (!$splitOk && !empty($attempt) && $left > 0.001) {
                    break;
                }

                $cells[$idx]['remaining']   -= $take;
                $cells[$idx]['used']        += $take;
                $cells[$idx]['product_uuid'] = $demand['product_uuid'];
                $left                       -= $take;

                $attempt[] = [
                    'order_uuid'       => $demand['order_uuid'],
                    'order_id'         => $demand['order_id'],
                    'entity_uuid'      => $demand['entity_uuid'] ?? null,
                    'product_uuid'     => $demand['product_uuid'],
                    'compartment_uuid' => $cells[$idx]['compartment']->uuid,
                    'planned_volume'   => round($take, 3),
                    'sequence'         => $cells[$idx]['compartment']->sequence,
                ];
            }

            if ($left > 0.001) {
                $reason = !$splitOk && !empty($attempt) ? 'split_not_allowed' : 'insufficient_capacity';
                if ($allowPartial) {
                    // roll back this line and keep packing the rest
                    $cells     = $snapshot;
                    $skipped[] = ['order_id' => $demand['order_id'], 'reason' => $reason];
                    continue;
                }

                return ['ok' => false, 'lines' => [], 'remaining' => 0, 'reason' => $reason, 'order_id' => $demand['order_id']];
            }

            $packed = array_merge($packed, $attempt);
        }

        if (empty($packed)) {
            return ['ok' => false, 'lines' => [], 'remaining' => 0, 'reason' => $skipped[0]['reason'] ?? 'nothing_to_pack', 'skipped' => $skipped];
        }

        $remaining = array_sum(array_column($cells, 'remaining'));

        return [
            'ok'        => true,
            'lines'     => $packed,
            'remaining' => round($remaining, 3),
            'capacity'  => round((float) $compartments->sum(fn (Compartment $c) => $c->capacityLiters()), 3),
            'skipped'   => $skipped,
        ];
    }

    /**
     * Vehicles that can pack at least one of the given orders on their own.
     */
    public function feasibleVehicles(Collection $orders, Collection $vehicles): Collection
    {
        $demand = $this->demandLines($orders)->groupBy('order_uuid');

        return $vehicles->filter(function (Vehicle $vehicle) use ($demand) {
            foreach ($demand as $lines) {
                $result = $this->packVehicle($vehicle, $lines);
                if ($result['ok']) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * After a solver assigns orders to vehicles, validate and pack each group.
     *
     * @return array{packs: array<string, array>, unassigned: array<string>}
     */
    public function packAssignments(Collection $orders, Collection $vehicles, array $assignments): array
    {
        $ordersById   = $orders->keyBy('public_id');
        $vehiclesById = $vehicles->keyBy('public_id');
        $byVehicle    = [];
        $unassigned   = [];

        foreach ($assignments as $assignment) {
            $orderId   = $assignment['order_id'] ?? null;
            $vehicleId = $assignment['vehicle_id'] ?? null;
            if (!$orderId || !$vehicleId || !$ordersById->has($orderId) || !$vehiclesById->has($vehicleId)) {
                if ($orderId) {
                    $unassigned[] = $orderId;
                }
                continue;
            }
            $byVehicle[$vehicleId][] = $assignment;
        }

        $packs = [];
        foreach ($byVehicle as $vehicleId => $group) {
            $vehicle     = $vehiclesById->get($vehicleId);
            $groupOrders = collect($group)->map(fn ($a) => $ordersById->get($a['order_id']))->filter();
            $lines       = $this->demandLines($groupOrders);
            $result      = $this->packVehicle($vehicle, $lines);

            if (!$result['ok']) {
                $sorted = $groupOrders->sortByDesc(function (Order $order) use ($lines) {
                    return $lines->where('order_uuid', $order->uuid)->sum('liters');
                });

                $kept = collect();
                foreach ($sorted as $order) {
                    $attempt = $kept->push($order);
                    $try     = $this->packVehicle($vehicle, $this->demandLines($attempt));
                    if (!$try['ok']) {
                        $kept->pop();
                        $unassigned[] = $order->public_id;
                    }
                }

                $result = $this->packVehicle($vehicle, $this->demandLines($kept));
                if (!$result['ok']) {
                    foreach ($groupOrders as $order) {
                        $unassigned[] = $order->public_id;
                    }
                    continue;
                }
            }

            $packs[$vehicleId] = [
                'vehicle'     => $vehicle,
                'lines'       => $result['lines'],
                'remaining'   => $result['remaining'],
                'operation'   => EnergyOps::orderOperation($groupOrders->first()),
                'assignments' => array_values(array_filter($group, fn ($a) => !in_array($a['order_id'], $unassigned, true))),
            ];
        }

        return [
            'packs'      => $packs,
            'unassigned' => array_values(array_unique($unassigned)),
        ];
    }

    public function profileFor(Vehicle $vehicle): ?VehicleProfile
    {
        return VehicleProfile::query()
            ->where('company_uuid', $vehicle->company_uuid)
            ->where('vehicle_uuid', $vehicle->uuid)
            ->with(['operation', 'coupledAsset'])
            ->first();
    }

    /**
     * Active compartments a vehicle can load: its own cells plus the coupled trailer's when it is a tractor.
     */
    public function compartmentsFor(Vehicle $vehicle, ?VehicleProfile $profile = null): Collection
    {
        $profile = $profile ?? $this->profileFor($vehicle);

        return Compartment::query()
            ->where('company_uuid', $vehicle->company_uuid)
            ->where('status', 'active')
            ->where(function ($q) use ($vehicle, $profile) {
                $q->where(fn ($own) => $own->where('owner_type', Compartment::OWNER_VEHICLE)->where('owner_uuid', $vehicle->uuid));
                if ($profile?->is_tractor && $profile->coupled_asset_uuid) {
                    $q->orWhere(fn ($trailer) => $trailer->where('owner_type', Compartment::OWNER_ASSET)->where('owner_uuid', $profile->coupled_asset_uuid));
                }
            })
            ->with(['dedicatedProduct', 'lastProduct'])
            ->orderByRaw('CASE WHEN owner_type = ? THEN 0 ELSE 1 END', [Compartment::OWNER_VEHICLE])
            ->orderBy('sequence')
            ->get();
    }

    public function applySkillsAndCapacity(Collection $orders, Collection $vehicles): void
    {
        $demand = $this->demandLines($orders);

        foreach ($orders as $order) {
            $skills = $demand->where('order_uuid', $order->uuid)
                ->flatMap(fn ($line) => $line['product'] ? EnergyOps::productSkills($line['product']) : [])
                ->filter()
                ->unique()
                ->values()
                ->all();

            $order->required_skills = array_values(array_unique(array_merge($order->required_skills ?? [], $skills)));
            $liters                 = $demand->where('order_uuid', $order->uuid)->sum('liters');
            $cube                   = EnergyOps::volumeAsCubeMeters($liters);

            if ($order->payload?->entities) {
                foreach ($order->payload->entities as $entity) {
                    $entity->length          = $cube['length'];
                    $entity->width           = $cube['width'];
                    $entity->height          = $cube['height'];
                    $entity->dimensions_unit = $cube['dimensions_unit'];
                }
            }
        }

        foreach ($vehicles as $vehicle) {
            $profile = $this->profileFor($vehicle);
            $cells   = $this->compartmentsFor($vehicle, $profile);
            $liters  = $cells->sum(fn (Compartment $c) => $c->availableVolumeLiters());
            $skills  = array_filter([
                $profile?->operation?->code,
            ]);

            $vehicle->skills                  = array_values(array_unique(array_merge($vehicle->skills ?? [], $skills)));
            $vehicle->payload_capacity_volume = $liters / 1000;
            if ($profile?->max_payload_kg) {
                $vehicle->payload_capacity = $profile->max_payload_kg;
            }
        }
    }

    /**
     * Compartments that may take `$product`, best first: dedicated/same-product cells win, then the
     * smallest cell that fits the whole line (avoids needless splits), then the largest partial cells.
     */
    protected function candidateIndexes(array $cells, Product $product, float $liters = 0.0): array
    {
        $scored = [];
        foreach ($cells as $idx => $cell) {
            /** @var Compartment $compartment */
            $compartment = $cell['compartment'];
            if ($cell['remaining'] <= 0) {
                continue;
            }
            if (!$this->compartmentAccepts($compartment, $product, $cell['product_uuid'], (float) $cell['used'])) {
                continue;
            }

            $score = 0;
            if ($compartment->dedicated_product_uuid === $product->uuid) {
                $score += 100;
            }
            if ($cell['product_uuid'] === $product->uuid) {
                $score += 50;
            }
            if ((float) $compartment->residual_volume <= 0) {
                $score += 10;
            }
            $scored[] = ['idx' => $idx, 'score' => $score, 'remaining' => $cell['remaining']];
        }

        usort($scored, function ($a, $b) use ($liters) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            $aFits = $liters > 0 && $a['remaining'] + 0.001 >= $liters;
            $bFits = $liters > 0 && $b['remaining'] + 0.001 >= $liters;
            if ($aFits !== $bFits) {
                return $aFits ? -1 : 1;
            }

            // both fit: tightest fit first; neither fits: biggest first
            return $aFits ? $a['remaining'] <=> $b['remaining'] : $b['remaining'] <=> $a['remaining'];
        });

        return array_column($scored, 'idx');
    }

    /**
     * Whether a compartment can take `$product` given what it currently holds.
     *
     * @param string|null $currentProductUuid product in the cell (residual or already packed in this run)
     * @param float       $usedInThisPack     volume already assigned to the cell during this packing run
     */
    protected function compartmentAccepts(Compartment $compartment, Product $product, ?string $currentProductUuid, float $usedInThisPack = 0.0): bool
    {
        if ($compartment->dedicated_product_uuid && $compartment->dedicated_product_uuid !== $product->uuid) {
            return false;
        }

        // Never mix two products in one cell during the same load
        if ($usedInThisPack > 0 && $currentProductUuid && $currentProductUuid !== $product->uuid) {
            return false;
        }

        // Residual of another product on board: governed by compatibility rules
        if ((float) $compartment->residual_volume > 0) {
            $onBoard = $currentProductUuid ?? $compartment->last_product_uuid;
            if ($onBoard && $onBoard !== $product->uuid) {
                return $this->reloadAllowed($onBoard, $product);
            }

            return true;
        }

        // Empty cell that last carried a different product
        if ($compartment->last_product_uuid && $compartment->last_product_uuid !== $product->uuid) {
            if ($compartment->requires_flush) {
                return false;
            }

            return $this->productChangeAllowed($compartment->last_product_uuid, $product);
        }

        return true;
    }

    /**
     * Product change on an *empty* compartment: forbidden rules block, flush-required rules
     * block until the compartment has been flushed (`flush()` clears `last_product_uuid`).
     */
    protected function productChangeAllowed(string $fromProductUuid, Product $toProduct): bool
    {
        $rule = $this->ruleFor($fromProductUuid, $toProduct);
        if (!$rule) {
            $from = Product::where('uuid', $fromProductUuid)->first();

            return !$from || $from->operation_uuid === $toProduct->operation_uuid;
        }

        if ($rule->same_family_only) {
            $from = Product::where('uuid', $fromProductUuid)->first();
            if ($from && $from->family !== $toProduct->family) {
                return false;
            }
        }

        return !$rule->isForbidden() && !$rule->requiresFlush();
    }

    protected function ruleFor(string $fromProductUuid, Product $toProduct): ?CompatibilityRule
    {
        $from = Product::where('uuid', $fromProductUuid)->first();

        return CompatibilityRule::query()
            ->where('company_uuid', $toProduct->company_uuid)
            ->where('status', 'active')
            ->where(function ($q) use ($fromProductUuid, $toProduct, $from) {
                $q->where(function ($inner) use ($fromProductUuid, $toProduct) {
                    $inner->where('from_product_uuid', $fromProductUuid)
                        ->where('to_product_uuid', $toProduct->uuid);
                });
                if ($from?->operation_uuid && $toProduct->operation_uuid) {
                    $q->orWhere(function ($inner) use ($from, $toProduct) {
                        $inner->whereNull('from_product_uuid')
                            ->where('from_operation_uuid', $from->operation_uuid)
                            ->where('to_operation_uuid', $toProduct->operation_uuid);
                    });
                }
            })
            ->orderByRaw('CASE WHEN from_product_uuid IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    /**
     * Loading `$toProduct` on top of residual `$fromProductUuid`.
     */
    protected function reloadAllowed(string $fromProductUuid, Product $toProduct): bool
    {
        if ($fromProductUuid === $toProduct->uuid) {
            return true;
        }

        $from = Product::where('uuid', $fromProductUuid)->first();
        if ($from && $from->operation_uuid !== $toProduct->operation_uuid) {
            return false;
        }

        $rule = $this->ruleFor($fromProductUuid, $toProduct);
        if (!$rule) {
            // No explicit rule: only same-operation products may share a compartment with residual
            return $from?->operation_uuid === $toProduct->operation_uuid;
        }

        if ($rule->same_family_only && $from && $from->family !== $toProduct->family) {
            return false;
        }

        // Residual of another product present: a flush is required first, so the compartment can't take it now
        if ($rule->isForbidden() || $rule->requiresFlush()) {
            return false;
        }

        return $rule->action === CompatibilityRule::ALLOWED;
    }

    protected function resolveProduct(Order $order, $entity): ?Product
    {
        $uuid = $entity->getMeta('energyops_product_uuid') ?? $entity->getMeta('product_uuid');
        if ($uuid) {
            return Product::where('company_uuid', $order->company_uuid)->where(fn ($q) => $q->where('uuid', $uuid)->orWhere('public_id', $uuid))->first();
        }

        $code = $entity->sku ?: $entity->getMeta('product_code') ?: $entity->getMeta('sku');
        if ($code) {
            return Product::where('company_uuid', $order->company_uuid)->where('code', Str::upper($code))->first()
                ?? Product::where('company_uuid', $order->company_uuid)->where('code', $code)->first();
        }

        return null;
    }

    protected function lineFromMeta(Order $order, ?Operation $operation): array
    {
        $product = null;
        $uuid    = $order->getMeta('energyops_product_uuid');
        if ($uuid) {
            $product = Product::where('uuid', $uuid)->first();
        }

        $qty    = (float) ($order->getMeta('volume') ?? $order->getMeta('qty') ?? 0);
        $unit   = (string) ($order->getMeta('unit') ?? $product?->default_unit ?? $operation?->unit ?? 'L');
        $liters = EnergyOps::toLiters($qty, $unit, $product);
        $tank   = $order->getMeta('energyops_customer_tank_uuid');

        return [
            'order'          => $order,
            'order_uuid'     => $order->uuid,
            'order_id'       => $order->public_id,
            'entity'         => null,
            'entity_uuid'    => null,
            'product'        => $product,
            'product_uuid'   => $product?->uuid,
            'operation'      => $operation ?? $product?->operation,
            'operation_uuid' => $operation?->uuid ?? $product?->operation_uuid,
            'liters'         => $liters,
            'unit'           => $unit,
            'tank_uuid'      => $tank,
            'ullage_ok'      => $this->ullageAllows($order, $product, $liters, $operation, $tank),
        ];
    }

    protected function ullageAllows(Order $order, ?Product $product, float $liters, ?Operation $operation, ?string $tankUuid = null): bool
    {
        if (!$product) {
            return true;
        }

        $tank = null;
        if ($tankUuid) {
            $tank = CustomerTank::where('company_uuid', $order->company_uuid)->where(fn ($q) => $q->where('uuid', $tankUuid)->orWhere('public_id', $tankUuid))->first();
            // A tank picked for a different product cannot govern this line's ullage
            if ($tank && $tank->product_uuid && $tank->product_uuid !== $product->uuid) {
                $tank = null;
            }
        }

        $placeUuid = $order->payload?->dropoff_uuid ?? $order->payload?->dropoff?->uuid;
        if (!$tank && $placeUuid) {
            $tank = CustomerTank::query()
                ->where('company_uuid', $order->company_uuid)
                ->where('place_uuid', $placeUuid)
                ->where('product_uuid', $product->uuid)
                ->first();
        }

        // No tank, or a tank whose capacity has not been surveyed yet: nothing to enforce
        if (!$tank || (float) $tank->capacity_volume <= 0) {
            return true;
        }

        // tank volumes are in the tank's unit; demand is in liters
        $ullageLiters = EnergyOps::toLiters((float) $tank->ullage, (string) ($tank->unit ?: 'L'), $product);

        return $liters <= $ullageLiters + 0.001;
    }
}
