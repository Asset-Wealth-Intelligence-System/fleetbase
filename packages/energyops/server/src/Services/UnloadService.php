<?php

namespace Fleetbase\EnergyOps\Services;

use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\CustomerTank;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Models\Unload;
use Fleetbase\EnergyOps\Models\UnloadLine;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DESCARGA lifecycle: prefill from the compartments, create as draft, confirm (compartments emptied,
 * load plan closed, inventory tank credited) or void (everything reversed).
 */
class UnloadService
{
    /**
     * Proposed descarga for a vehicle: one line per compartment holding product,
     * `before = residual`, `unloaded = residual`, `remaining = 0`.
     */
    public function prefill(Vehicle $vehicle, ?string $companyUuid = null): array
    {
        $companyUuid = $companyUuid ?? $vehicle->company_uuid;
        $plan        = $this->openPlanFor($vehicle, $companyUuid);
        $inventory   = EnergyOps::inventoryPlace($companyUuid);
        $lines       = $this->compartmentsFor($vehicle)->map(function (Compartment $compartment, int $index) {
            $residual = round((float) $compartment->residual_volume, 3);

            return [
                'compartment_uuid' => $compartment->uuid,
                'compartment_code' => $compartment->code,
                'compartment_name' => $compartment->name,
                'product_uuid'     => $compartment->last_product_uuid,
                'product_name'     => $compartment->lastProduct?->name ?? $compartment->lastProduct?->code,
                'capacity_volume'  => (float) $compartment->capacity_volume,
                'before_volume'    => $residual,
                'unloaded_volume'  => $residual,
                'remaining_volume' => 0.0,
                'unit'             => $compartment->unit ?: 'gal',
                'sequence'         => (int) ($compartment->sequence ?? $index + 1),
            ];
        })->values();

        return [
            'vehicle_uuid'         => $vehicle->uuid,
            'driver_uuid'          => $vehicle->driver?->uuid,
            'load_plan_uuid'       => $plan?->uuid,
            'load_plan_id'         => $plan?->public_id,
            'manifest_uuid'        => $plan?->manifest_uuid,
            'inventory_place_uuid' => $inventory?->uuid,
            'inventory_place'      => $inventory ? ['uuid' => $inventory->uuid, 'public_id' => $inventory->public_id, 'name' => $inventory->name, 'address' => $inventory->address] : null,
            'inventory_tank_uuid'  => $inventory ? $this->inventoryTankFor($inventory, $lines->pluck('product_uuid')->filter()->first())?->uuid : null,
            'unit'                 => $lines->first()['unit'] ?? 'gal',
            'unloaded_at'          => Carbon::now()->toIso8601String(),
            'lines'                => $lines->all(),
            'total_before_volume'  => round((float) $lines->sum('before_volume'), 3),
        ];
    }

    /**
     * Create a draft (or directly confirmed) descarga from user input.
     *
     * Input: vehicle_uuid, inventory_place_uuid?, load_plan_uuid?, unloaded_at?, notes?, source?, lines: [{compartment_uuid, unloaded_volume, remaining_volume?}], confirm?: bool
     */
    public function create(array $input, string $companyUuid, ?string $userUuid = null): Unload
    {
        $vehicle = Vehicle::where('company_uuid', $companyUuid)
            ->where(fn ($q) => $q->where('uuid', $input['vehicle_uuid'] ?? '')->orWhere('public_id', $input['vehicle_uuid'] ?? ''))
            ->first();
        if (!$vehicle) {
            throw ValidationException::withMessages(['vehicle_uuid' => 'Vehicle not found.']);
        }

        $inventory = !empty($input['inventory_place_uuid'])
            ? EnergyOps::placeOfType($companyUuid, $input['inventory_place_uuid'], EnergyOps::PLACE_TYPE_INVENTORY)
            : EnergyOps::inventoryPlace($companyUuid);
        if (!$inventory) {
            throw ValidationException::withMessages(['inventory_place_uuid' => 'Select the inventory site (place of type `inventory`) receiving the product.']);
        }

        $plan = !empty($input['load_plan_uuid'])
            ? LoadPlan::where('company_uuid', $companyUuid)->where(fn ($q) => $q->where('uuid', $input['load_plan_uuid'])->orWhere('public_id', $input['load_plan_uuid']))->first()
            : $this->openPlanFor($vehicle, $companyUuid);

        $compartments = $this->compartmentsFor($vehicle)->keyBy('uuid');
        $inputLines   = collect($input['lines'] ?? []);

        return DB::transaction(function () use ($input, $companyUuid, $userUuid, $vehicle, $inventory, $plan, $compartments, $inputLines) {
            $unload = Unload::create([
                'company_uuid'         => $companyUuid,
                'created_by_uuid'      => $userUuid,
                'recorded_by_uuid'     => $input['recorded_by_uuid'] ?? $userUuid,
                'vehicle_uuid'         => $vehicle->uuid,
                'driver_uuid'          => $input['driver_uuid'] ?? $vehicle->driver?->uuid,
                'load_plan_uuid'       => $plan?->uuid,
                'manifest_uuid'        => $input['manifest_uuid'] ?? $plan?->manifest_uuid,
                'inventory_place_uuid' => $inventory->uuid,
                'unit'                 => $input['unit'] ?? ($compartments->first()?->unit ?: 'gal'),
                'source'               => in_array($input['source'] ?? null, Unload::SOURCES, true) ? $input['source'] : Unload::SOURCE_CONSOLE,
                'status'               => Unload::STATUS_DRAFT,
                'unloaded_at'          => !empty($input['unloaded_at']) ? Carbon::parse($input['unloaded_at']) : Carbon::now(),
                'notes'                => $input['notes'] ?? null,
                'meta'                 => $input['meta'] ?? [],
            ]);

            $sequence = 1;
            foreach ($compartments as $compartment) {
                $row = $inputLines->first(fn ($line) => ($line['compartment_uuid'] ?? null) === $compartment->uuid || ($line['compartment_uuid'] ?? null) === $compartment->public_id);
                $before   = round((float) $compartment->residual_volume, 3);
                $unloaded = $row !== null && isset($row['unloaded_volume']) ? max(0, (float) $row['unloaded_volume']) : $before;
                $unloaded = min($unloaded, $before);
                $remaining = $row !== null && isset($row['remaining_volume']) ? max(0, (float) $row['remaining_volume']) : max(0, $before - $unloaded);

                if ($before <= 0 && $unloaded <= 0 && $row === null) {
                    // empty compartment nobody touched: not part of the descarga
                    continue;
                }

                UnloadLine::create([
                    'company_uuid'     => $companyUuid,
                    'unload_uuid'      => $unload->uuid,
                    'compartment_uuid' => $compartment->uuid,
                    'product_uuid'     => $row['product_uuid'] ?? $compartment->last_product_uuid,
                    'before_volume'    => $before,
                    'unloaded_volume'  => round($unloaded, 3),
                    'remaining_volume' => round($remaining, 3),
                    'unit'             => $compartment->unit ?: 'gal',
                    'sequence'         => $sequence++,
                    'meta'             => $row['meta'] ?? [],
                ]);
            }

            $unload->load('lines');
            $unload->inventory_tank_uuid = $input['inventory_tank_uuid'] ?? $this->inventoryTankFor($inventory, $unload->lines->pluck('product_uuid')->filter()->first())?->uuid;
            $unload->refreshTotals()->save();

            if (!empty($input['confirm'])) {
                return $this->confirm($unload, $userUuid);
            }

            return $unload->fresh(['vehicle', 'inventoryPlace', 'lines', 'files']);
        });
    }

    /**
     * Update a draft's lines / header before confirming.
     */
    public function update(Unload $unload, array $input): Unload
    {
        if (!$unload->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Only draft descargas can be edited.']);
        }

        return DB::transaction(function () use ($unload, $input) {
            foreach (['notes', 'unit', 'manifest_uuid', 'driver_uuid', 'recorded_by_uuid'] as $key) {
                if (array_key_exists($key, $input)) {
                    $unload->{$key} = $input[$key];
                }
            }
            if (!empty($input['unloaded_at'])) {
                $unload->unloaded_at = Carbon::parse($input['unloaded_at']);
            }
            if (!empty($input['inventory_place_uuid'])) {
                $inventory = EnergyOps::placeOfType($unload->company_uuid, $input['inventory_place_uuid'], EnergyOps::PLACE_TYPE_INVENTORY);
                if ($inventory) {
                    $unload->inventory_place_uuid = $inventory->uuid;
                }
            }
            if (array_key_exists('inventory_tank_uuid', $input)) {
                $unload->inventory_tank_uuid = $input['inventory_tank_uuid'];
            }
            if (isset($input['meta']) && is_array($input['meta'])) {
                $unload->meta = array_merge(is_array($unload->meta) ? $unload->meta : [], $input['meta']);
            }

            $unload->loadMissing('lines');
            foreach ((array) ($input['lines'] ?? []) as $row) {
                $line = $unload->lines->first(fn (UnloadLine $l) => $l->uuid === ($row['uuid'] ?? null) || $l->compartment_uuid === ($row['compartment_uuid'] ?? null));
                if (!$line) {
                    continue;
                }
                if (isset($row['unloaded_volume'])) {
                    $line->unloaded_volume = min(max(0, (float) $row['unloaded_volume']), (float) $line->before_volume);
                }
                $line->remaining_volume = isset($row['remaining_volume']) ? max(0, (float) $row['remaining_volume']) : max(0, (float) $line->before_volume - (float) $line->unloaded_volume);
                $line->save();
            }

            $unload->unsetRelation('lines');
            $unload->refreshTotals()->save();

            return $unload->fresh(['vehicle', 'inventoryPlace', 'lines', 'files']);
        });
    }

    /**
     * Confirm: compartments keep only `remaining`, the open load plan is closed and the inventory tank
     * receives the unloaded volume.
     */
    public function confirm(Unload $unload, ?string $userUuid = null): Unload
    {
        if (!$unload->isDraft()) {
            throw ValidationException::withMessages(['status' => 'Descarga is already ' . $unload->status . '.']);
        }

        $this->assertProof($unload);

        return DB::transaction(function () use ($unload, $userUuid) {
            $unload->loadMissing(['lines.compartment']);

            $creditedLiters = 0.0;
            foreach ($unload->lines as $line) {
                $compartment = $line->compartment ?? Compartment::where('uuid', $line->compartment_uuid)->first();
                if (!$compartment) {
                    continue;
                }

                $line->setMeta('compartment_residual_before', (float) $compartment->residual_volume);
                $line->save();

                $compartment->residual_volume = round((float) $line->remaining_volume, 3);
                if ((float) $line->remaining_volume <= 0) {
                    // product fully removed; keep last_product so compatibility rules still apply on the next load
                    $compartment->residual_volume = 0;
                }
                $compartment->save();

                $creditedLiters += EnergyOps::toLiters((float) $line->unloaded_volume, (string) ($line->unit ?: 'gal'));
            }

            $tank = $unload->inventory_tank_uuid ? CustomerTank::where('uuid', $unload->inventory_tank_uuid)->first() : null;
            if ($tank) {
                $tankUnits = EnergyOps::fromLiters($creditedLiters, (string) ($tank->unit ?: 'gal'), $tank->product);
                $unload->setMeta('inventory_tank_before', (float) $tank->last_known_volume);
                $tank->last_known_volume = round((float) $tank->last_known_volume + $tankUnits, 3);
                $tank->last_delivery_at  = $unload->unloaded_at ?? Carbon::now();
                $tank->save();
                $unload->setMeta('inventory_tank_credited', round($tankUnits, 3));
            }

            if ($unload->load_plan_uuid) {
                $plan = LoadPlan::where('uuid', $unload->load_plan_uuid)->first();
                if ($plan && $plan->isOpen()) {
                    $plan->setMeta('closed_by_unload_uuid', $unload->uuid);
                    app(LoadPlanService::class)->close($plan);
                }
            }

            $unload->status           = Unload::STATUS_CONFIRMED;
            $unload->confirmed_at     = Carbon::now();
            $unload->recorded_by_uuid = $unload->recorded_by_uuid ?? $userUuid;
            $unload->setMeta('confirmed_by_uuid', $userUuid);
            $unload->unsetRelation('lines');
            $unload->refreshTotals()->save();

            return $unload->fresh(['vehicle', 'inventoryPlace', 'lines', 'files']);
        });
    }

    /**
     * Void a confirmed descarga: put the product back on the compartments and debit the inventory tank.
     */
    public function void(Unload $unload, ?string $reason = null, ?string $userUuid = null): Unload
    {
        if ($unload->status === Unload::STATUS_VOIDED) {
            throw ValidationException::withMessages(['status' => 'Descarga is already voided.']);
        }

        return DB::transaction(function () use ($unload, $reason, $userUuid) {
            if ($unload->isConfirmed()) {
                $unload->loadMissing(['lines.compartment']);
                foreach ($unload->lines as $line) {
                    $compartment = $line->compartment ?? Compartment::where('uuid', $line->compartment_uuid)->first();
                    if (!$compartment) {
                        continue;
                    }
                    $restore = $line->getMeta('compartment_residual_before');
                    $compartment->residual_volume = round((float) ($restore ?? $line->before_volume), 3);
                    if ($line->product_uuid) {
                        $compartment->last_product_uuid = $line->product_uuid;
                    }
                    $compartment->save();
                }

                $credited = (float) $unload->getMeta('inventory_tank_credited', 0);
                $tank     = $unload->inventory_tank_uuid ? CustomerTank::where('uuid', $unload->inventory_tank_uuid)->first() : null;
                if ($tank && $credited > 0) {
                    $tank->last_known_volume = round(max(0, (float) $tank->last_known_volume - $credited), 3);
                    $tank->save();
                }
            }

            $unload->status    = Unload::STATUS_VOIDED;
            $unload->voided_at = Carbon::now();
            $unload->setMeta('void_reason', $reason);
            $unload->setMeta('voided_by_uuid', $userUuid);
            $unload->save();

            return $unload->fresh(['vehicle', 'inventoryPlace', 'lines', 'files']);
        });
    }

    /**
     * Latest open load plan (loaded / in transit preferred) for the vehicle.
     */
    public function openPlanFor(Vehicle $vehicle, ?string $companyUuid = null): ?LoadPlan
    {
        return LoadPlan::query()
            ->where('company_uuid', $companyUuid ?? $vehicle->company_uuid)
            ->where('vehicle_uuid', $vehicle->uuid)
            ->open()
            ->orderByRaw("CASE WHEN status IN ('in_transit','loaded') THEN 0 ELSE 1 END")
            ->latest()
            ->first();
    }

    /**
     * Inventory tank at the descarga site, preferring the one for the product being returned.
     */
    public function inventoryTankFor(Place $inventory, ?string $productUuid = null): ?CustomerTank
    {
        $query = CustomerTank::query()->where('place_uuid', $inventory->uuid)->where('status', 'active');
        if ($productUuid) {
            $match = (clone $query)->where('product_uuid', $productUuid)->first();
            if ($match) {
                return $match;
            }
        }

        return $query->orderBy('created_at')->first();
    }

    protected function compartmentsFor(Vehicle $vehicle): Collection
    {
        $profile = VehicleProfile::where('vehicle_uuid', $vehicle->uuid)->first();
        if ($profile) {
            return $profile->compartments()->load('lastProduct');
        }

        return Compartment::forOwner(Compartment::OWNER_VEHICLE, $vehicle->uuid)->where('status', 'active')->orderBy('sequence')->with('lastProduct')->get();
    }

    protected function assertProof(Unload $unload): void
    {
        if (!EnergyOps::requireUnloadProof($unload->company_uuid)) {
            return;
        }

        if ($unload->files()->count() === 0) {
            throw ValidationException::withMessages([
                'proof' => 'Attach a photo or signature of the descarga before confirming.',
            ]);
        }
    }
}
