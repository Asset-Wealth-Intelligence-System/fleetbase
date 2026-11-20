<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Api\v1;

use Fleetbase\EnergyOps\Http\Controllers\Api\v1\Concerns\AttachesProofFiles;
use Fleetbase\EnergyOps\Http\Requests\CreateUnloadRequest;
use Fleetbase\EnergyOps\Http\Resources\v1\Unload as UnloadResource;
use Fleetbase\EnergyOps\Models\Unload;
use Fleetbase\EnergyOps\Services\UnloadService;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Consumable DESCARGA API: drivers / inventory staff record the end-of-day unload from a device.
 */
class UnloadController extends ApiResourceController
{
    use AttachesProofFiles;

    protected string $model          = Unload::class;
    protected string $resource       = UnloadResource::class;
    protected ?string $createRequest = CreateUnloadRequest::class;
    protected array $with            = ['vehicle', 'driver', 'inventoryPlace', 'inventoryTank', 'lines.compartment', 'lines.product', 'files'];
    protected string $label          = 'Unload';

    public function create(Request $request)
    {
        $input = $this->validated($request, $this->createRequest);
        if (!is_array($input)) {
            return $input;
        }

        $input['source'] = $input['source'] ?? Unload::SOURCE_API;
        $unload          = app(UnloadService::class)->create($input, session('company'), $request->user()?->uuid);

        return new UnloadResource($unload->load($this->with));
    }

    /**
     * Proposed descarga for a vehicle (public id or uuid).
     */
    public function prefill(Request $request)
    {
        $vehicleId = (string) ($request->input('vehicle') ?? $request->input('vehicle_uuid') ?? '');
        $vehicle   = Vehicle::query()
            ->where('company_uuid', session('company'))
            ->where(fn ($q) => $q->where('uuid', $vehicleId)->orWhere('public_id', $vehicleId))
            ->first();

        if (!$vehicle) {
            return response()->apiError('Vehicle resource not found.', 404);
        }

        return response()->json(app(UnloadService::class)->prefill($vehicle, session('company')));
    }

    public function confirm(string $id, Request $request)
    {
        try {
            /** @var Unload $unload */
            $unload = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError('Unload resource not found.', 404);
        }

        if (!$unload->isDraft()) {
            return response()->apiError('Unload is already ' . $unload->status . '.');
        }

        $unload = app(UnloadService::class)->confirm($unload, $request->user()?->uuid);

        return new UnloadResource($unload->load($this->with));
    }

    public function void(string $id, Request $request)
    {
        try {
            /** @var Unload $unload */
            $unload = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError('Unload resource not found.', 404);
        }

        $unload = app(UnloadService::class)->void($unload, $request->input('reason'), $request->user()?->uuid);

        return new UnloadResource($unload->load($this->with));
    }

    /**
     * Attach a proof of unload (photo or signature image).
     */
    public function uploadFile(string $id, Request $request)
    {
        try {
            $unload = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError('Unload resource not found.', 404);
        }

        return $this->attachProofFile($request, $unload, 'uploads/energyops/unloads', 'unload_photo', Unload::PROOF_FILE_TYPES);
    }

    protected function publicIdMap(): array
    {
        return [
            'vehicle'         => ['vehicle_uuid', Vehicle::class],
            'load_plan'       => ['load_plan_uuid', \Fleetbase\EnergyOps\Models\LoadPlan::class],
            'inventory_place' => ['inventory_place_uuid', \Fleetbase\FleetOps\Models\Place::class],
        ];
    }
}
