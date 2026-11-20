<?php

namespace Fleetbase\EnergyOps\Orchestration\Engines;

use Fleetbase\EnergyOps\Services\CompartmentPacker;
use Fleetbase\EnergyOps\Services\LoadPlanService;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Orchestration\Contracts\OrchestrationEngineInterface;
use Fleetbase\FleetOps\Orchestration\Engines\VroomOrchestrationEngine;
use Illuminate\Support\Collection;

/**
 * Fleet-Ops orchestration engine that packs energy orders into tanker compartments,
 * routes the packed groups with VROOM (falling back to first-fit) and persists load plans.
 */
class CompartmentAllocationEngine implements OrchestrationEngineInterface
{
    public function __construct(
        protected CompartmentPacker $packer,
        protected LoadPlanService $loadPlans,
        protected VroomOrchestrationEngine $vroom
    ) {
    }

    public function getName(): string
    {
        return 'Compartment packer';
    }

    public function getIdentifier(): string
    {
        return EnergyOps::ENGINE_ID;
    }

    public function allocate(Collection $orders, Collection $vehicles, array $options = []): array
    {
        $energyOrders = $orders->filter(fn ($order) => EnergyOps::isEnergyOrder($order))->values();
        $otherOrders  = $orders->reject(fn ($order) => EnergyOps::isEnergyOrder($order))->values();

        $unassigned = [];
        $summary    = [
            'engine'     => EnergyOps::ENGINE_ID,
            'packed'     => 0,
            'load_plans' => [],
            'skipped'    => $otherOrders->pluck('public_id')->all(),
            'warnings'   => [],
        ];

        foreach ($otherOrders as $order) {
            $unassigned[] = $order->public_id;
        }

        if ($energyOrders->isEmpty()) {
            return [
                'assignments' => [],
                'unassigned'  => $unassigned,
                'summary'     => $summary,
            ];
        }

        $demand = $this->packer->demandLines($energyOrders);
        foreach ($energyOrders as $order) {
            $orderLines = $demand->where('order_uuid', $order->uuid);
            if ($orderLines->isEmpty()) {
                $unassigned[]           = $order->public_id;
                $summary['warnings'][]  = $order->public_id . ' has no energy product/qty';
            }
        }

        $packableOrders = $energyOrders->filter(fn ($order) => $demand->where('order_uuid', $order->uuid)->isNotEmpty())->values();
        if ($packableOrders->isEmpty()) {
            return [
                'assignments' => [],
                'unassigned'  => array_values(array_unique($unassigned)),
                'summary'     => array_merge($summary, ['reason' => 'no_energy_demand']),
            ];
        }

        // Only company vehicles with an Energy Ops profile can carry bulk product
        $companyUuid = $packableOrders->first()->company_uuid;
        $vehicles    = $vehicles->filter(fn ($vehicle) => $vehicle->company_uuid === $companyUuid && $this->packer->profileFor($vehicle))->values();
        $feasible    = $this->packer->feasibleVehicles($packableOrders, $vehicles);

        if ($feasible->isEmpty()) {
            return [
                'assignments' => [],
                'unassigned'  => array_values(array_unique(array_merge($unassigned, $packableOrders->pluck('public_id')->all()))),
                'summary'     => array_merge($summary, ['reason' => 'no_feasible_vehicles']),
            ];
        }

        $this->packer->applySkillsAndCapacity($packableOrders, $feasible);

        try {
            $routed = $this->vroom->allocate($packableOrders, $feasible, $options);
        } catch (\Throwable $e) {
            $routed = $this->fallbackAllocate($packableOrders, $feasible);
            $summary['vroom_error'] = $e->getMessage();
            $summary['fallback']    = 'first_fit';
        }

        $validated = $this->packer->packAssignments(
            $packableOrders,
            $feasible,
            $routed['assignments'] ?? []
        );

        // Optional depot override from the pack form: `options.depot_place_uuid` (global) or
        // `options.depots[vehicle public id]` (per vehicle). Otherwise the vehicle's habitual depot is used.
        foreach ($validated['packs'] as $vehicleId => &$pack) {
            $depot = data_get($options, 'depots.' . $vehicleId) ?? ($options['depot_place_uuid'] ?? null);
            if ($depot) {
                $pack['depot_place_uuid'] = $depot;
            }
        }
        unset($pack);

        $plans                 = $this->loadPlans->persistPacks($validated['packs'], $companyUuid);
        $summary['packed']     = $plans->count();
        $summary['load_plans'] = $plans->map(fn ($plan) => ['id' => $plan->public_id, 'uuid' => $plan->uuid, 'vehicle_uuid' => $plan->vehicle_uuid])->values()->all();

        // Tag each assignment with the load plan it belongs to
        $plansByVehicle = $plans->keyBy('vehicle_uuid');
        foreach ($validated['packs'] as $vehicleId => &$pack) {
            $plan = $plansByVehicle->get($pack['vehicle']->uuid);
            foreach ($pack['assignments'] as &$assignment) {
                $assignment['load_plan_id']   = $plan?->public_id;
                $assignment['load_plan_uuid'] = $plan?->uuid;
            }
            unset($assignment);
        }
        unset($pack);

        $assignments = [];
        foreach ($validated['packs'] as $pack) {
            foreach ($pack['assignments'] as $assignment) {
                $assignments[] = $assignment;
            }
        }

        $unassigned = array_values(array_unique(array_merge(
            $unassigned,
            $routed['unassigned'] ?? [],
            $validated['unassigned']
        )));

        return [
            'assignments' => $assignments,
            'unassigned'  => $unassigned,
            'summary'     => array_merge($routed['summary'] ?? [], $summary),
        ];
    }

    /**
     * First-fit assignment used when VROOM is unavailable.
     */
    protected function fallbackAllocate(Collection $orders, Collection $vehicles): array
    {
        $assignments = [];
        $unassigned  = [];
        $used        = [];

        foreach ($orders as $order) {
            $placed = false;
            foreach ($vehicles as $vehicle) {
                $key = $vehicle->public_id;
                $try = $this->packer->packVehicle(
                    $vehicle,
                    $this->packer->demandLines(collect([$order])->concat(
                        collect($assignments)->where('vehicle_id', $key)->map(fn ($a) => $orders->firstWhere('public_id', $a['order_id']))->filter()
                    ))
                );
                if ($try['ok']) {
                    $assignments[] = [
                        'order_id'   => $order->public_id,
                        'vehicle_id' => $vehicle->public_id,
                        'driver_id'  => $vehicle->driver?->public_id,
                        'sequence'   => ($used[$key] ?? 0) + 1,
                    ];
                    $used[$key] = ($used[$key] ?? 0) + 1;
                    $placed     = true;
                    break;
                }
            }
            if (!$placed) {
                $unassigned[] = $order->public_id;
            }
        }

        return [
            'assignments' => $assignments,
            'unassigned'  => $unassigned,
            'summary'     => ['fallback' => 'first_fit'],
        ];
    }
}
