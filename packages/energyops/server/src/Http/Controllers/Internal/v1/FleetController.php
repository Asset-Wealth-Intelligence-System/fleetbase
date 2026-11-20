<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    /**
     * Energy summary keyed by vehicle uuid for the Fleet page: profile, operation,
     * compartment count and total capacity (own compartments + coupled trailer).
     *
     * Query: vehicles[] (uuid|public_id), operation (uuid), configured (bool)
     */
    public function summary(Request $request)
    {
        $companyUuid = session('company');
        // Accept `vehicles[]=a&vehicles[]=b` or a comma separated `vehicles=a,b` (URLSearchParams style)
        $ids = collect((array) $request->input('vehicles', []))
            ->flatMap(fn ($value) => is_string($value) ? explode(',', $value) : (array) $value)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            return response()->json([]);
        }

        $vehicles = Vehicle::query()
            ->where('company_uuid', $companyUuid)
            ->where(fn ($q) => $q->whereIn('uuid', $ids)->orWhereIn('public_id', $ids))
            ->get(['uuid', 'public_id']);

        $profiles = VehicleProfile::query()
            ->where('company_uuid', $companyUuid)
            ->whereIn('vehicle_uuid', $vehicles->pluck('uuid'))
            ->with('operation:uuid,code,name')
            ->get()
            ->keyBy('vehicle_uuid');

        $ownerUuids = $vehicles->pluck('uuid')->merge($profiles->pluck('coupled_asset_uuid')->filter())->unique()->values();

        $compartments = Compartment::query()
            ->where('company_uuid', $companyUuid)
            ->whereIn('owner_uuid', $ownerUuids)
            // `uuid` must be selected: Eloquent Collection::merge() dedupes by primary key,
            // so keyless models would collapse into a single compartment.
            ->get(['uuid', 'owner_uuid', 'capacity_volume', 'residual_volume', 'requires_flush', 'unit', 'max_fill_percent'])
            ->groupBy('owner_uuid');

        $operationFilter = $request->input('operation');
        $configured      = $request->has('configured') ? $request->boolean('configured') : null;

        $summary = [];
        foreach ($vehicles as $vehicle) {
            /** @var VehicleProfile|null $profile */
            $profile = $profiles->get($vehicle->uuid);
            $own     = $compartments->get($vehicle->uuid, collect());
            $trailer = $profile?->coupled_asset_uuid ? $compartments->get($profile->coupled_asset_uuid, collect()) : collect();
            $all     = $own->merge($trailer);

            $row = [
                'profile_uuid'       => $profile?->uuid,
                'profile_status'     => $profile ? ($all->isNotEmpty() ? 'configured' : 'no_compartments') : 'no_profile',
                'operation_uuid'     => $profile?->operation_uuid,
                'operation_name'     => $profile?->operation?->name,
                'operation_code'     => $profile?->operation?->code,
                'is_tractor'         => (bool) ($profile?->is_tractor ?? false),
                'coupled_asset_uuid' => $profile?->coupled_asset_uuid,
                'compartments_count' => $all->count(),
                // reported in liters regardless of each compartment's native unit
                'total_capacity'     => round((float) $all->sum(fn (Compartment $c) => $c->capacityLiters()), 2),
                'residual_volume'    => round((float) $all->sum(fn (Compartment $c) => $c->residualVolumeLiters()), 2),
                'capacity_unit'      => 'L',
                'needs_flush'        => $all->where('requires_flush', true)->count(),
            ];

            if ($operationFilter && $row['operation_uuid'] !== $operationFilter && $row['operation_code'] !== $operationFilter) {
                continue;
            }
            if ($configured === true && $row['profile_status'] !== 'configured') {
                continue;
            }
            if ($configured === false && $row['profile_status'] === 'configured') {
                continue;
            }

            $summary[$vehicle->uuid]      = $row;
            $summary[$vehicle->public_id] = $row;
        }

        return response()->json($summary);
    }

    /**
     * Operations available to assign to vehicles (small helper for selects).
     */
    public function operations()
    {
        return response()->json([
            'operations' => Operation::where('company_uuid', session('company'))->orderBy('name')->get(['uuid', 'public_id', 'code', 'name', 'family']),
        ]);
    }
}
