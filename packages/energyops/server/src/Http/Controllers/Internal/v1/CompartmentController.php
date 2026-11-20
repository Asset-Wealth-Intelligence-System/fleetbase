<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Requests\CreateCompartmentRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateCompartmentRequest;
use Fleetbase\EnergyOps\Http\Resources\v1\Compartment as CompartmentResource;
use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\FleetOps\Models\Asset;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompartmentController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'compartment';

    /**
     * The form request used to validate creation.
     */
    public $createRequest = CreateCompartmentRequest::class;

    /**
     * The form request used to validate updates.
     */
    public $updateRequest = UpdateCompartmentRequest::class;

    /**
     * List every compartment reachable from a vehicle or asset, including the coupled
     * trailer of a tractor when a vehicle is requested.
     */
    public function forOwner(Request $request, string $ownerType, string $ownerId)
    {
        $companyUuid = session('company');
        $normalized  = strtolower((string) $ownerType);
        $ownerUuids  = [];

        if (str_contains($normalized, 'vehicle')) {
            $vehicle = Vehicle::where('company_uuid', $companyUuid)
                ->where(fn ($q) => $q->where('uuid', $ownerId)->orWhere('public_id', $ownerId))
                ->first();
            if ($vehicle) {
                $ownerUuids[] = $vehicle->uuid;
                $profile      = VehicleProfile::where('company_uuid', $companyUuid)->where('vehicle_uuid', $vehicle->uuid)->first();
                if ($profile?->is_tractor && $profile->coupled_asset_uuid) {
                    $ownerUuids[] = $profile->coupled_asset_uuid;
                }
            }
        } elseif (str_contains($normalized, 'asset')) {
            $asset = Asset::where('company_uuid', $companyUuid)
                ->where(fn ($q) => $q->where('uuid', $ownerId)->orWhere('public_id', $ownerId))
                ->first();
            if ($asset) {
                $ownerUuids[] = $asset->uuid;
            }
        }

        if (empty($ownerUuids)) {
            return response()->json(['compartments' => []]);
        }

        $compartments = Compartment::query()
            ->where('company_uuid', $companyUuid)
            ->whereIn('owner_uuid', array_values(array_unique($ownerUuids)))
            ->with(['dedicatedProduct', 'lastProduct'])
            ->orderBy('sequence')
            ->get();

        CompartmentResource::wrap('compartments');

        return CompartmentResource::collection($compartments);
    }

    /**
     * Persist a new ordering for a set of compartments.
     *
     * Payload: { compartments: [{ uuid|id, sequence }] }
     */
    public function reorder(Request $request)
    {
        $companyUuid = session('company');
        $items       = collect($request->input('compartments', []))->filter(fn ($item) => is_array($item) && (isset($item['uuid']) || isset($item['id'])));

        if ($items->isEmpty()) {
            return response()->error('No compartments provided to reorder.');
        }

        DB::transaction(function () use ($items, $companyUuid) {
            foreach ($items->values() as $index => $item) {
                $id = $item['uuid'] ?? $item['id'];
                Compartment::query()
                    ->where('company_uuid', $companyUuid)
                    ->where(fn ($q) => $q->where('uuid', $id)->orWhere('public_id', $id))
                    ->update(['sequence' => (int) ($item['sequence'] ?? $index + 1)]);
            }
        });

        $ids          = $items->map(fn ($item) => $item['uuid'] ?? $item['id'])->all();
        $compartments = Compartment::query()
            ->where('company_uuid', $companyUuid)
            ->where(fn ($q) => $q->whereIn('uuid', $ids)->orWhereIn('public_id', $ids))
            ->with(['dedicatedProduct', 'lastProduct'])
            ->orderBy('sequence')
            ->get();

        CompartmentResource::wrap('compartments');

        return CompartmentResource::collection($compartments);
    }

    /**
     * Mark a compartment as flushed: clears residual, last product and the flush flag.
     */
    public function flush(Request $request, string $id)
    {
        $compartment = Compartment::query()
            ->where('company_uuid', session('company'))
            ->where(fn ($q) => $q->where('uuid', $id)->orWhere('public_id', $id))
            ->first();

        if (!$compartment) {
            return response()->error('Compartment not found.', 404);
        }

        $compartment->setMeta('last_flushed_at', now()->toIso8601String());
        $compartment->setMeta('last_flushed_by', $request->user()?->uuid);
        $compartment->flush();

        CompartmentResource::wrap('compartment');

        return new CompartmentResource($compartment->fresh(['dedicatedProduct', 'lastProduct']));
    }
}
