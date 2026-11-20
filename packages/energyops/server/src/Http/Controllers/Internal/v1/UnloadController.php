<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Requests\CreateUnloadRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateUnloadRequest;
use Fleetbase\EnergyOps\Http\Resources\v1\Unload as UnloadResource;
use Fleetbase\EnergyOps\Models\Unload;
use Fleetbase\EnergyOps\Services\UnloadService;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\Support\Auth;
use Illuminate\Http\Request;

/**
 * DESCARGAS — end-of-day unload of the compartments back to the inventory site.
 */
class UnloadController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'unload';

    /**
     * The form request used to validate creation.
     */
    public $createRequest = CreateUnloadRequest::class;

    /**
     * The form request used to validate updates.
     */
    public $updateRequest = UpdateUnloadRequest::class;

    /** Relations embedded in every single-unload response. */
    protected array $unloadRelations = ['vehicle', 'driver', 'inventoryPlace', 'inventoryTank', 'lines.compartment', 'lines.product', 'files'];

    /**
     * Create a descarga (draft, or confirmed when `confirm: true`) through the UnloadService
     * so the nested compartment lines are handled.
     */
    public function createRecord(Request $request)
    {
        if (!Auth::can('energy-ops create energy-unload')) {
            return response()->error('You do not have permission to create descargas.', 403);
        }

        $formRequest = CreateUnloadRequest::createFrom($request);
        $formRequest->setContainer(app());
        $input = $request->input('unload', $request->all());
        $validator = \Illuminate\Support\Facades\Validator::make($input, $formRequest->rules());
        if ($validator->fails()) {
            return response()->error($validator->errors()->all(), 422);
        }

        $unload = app(UnloadService::class)->create($validator->validated(), session('company'), $request->user()?->uuid);

        return $this->respondWithUnload($unload);
    }

    /**
     * Edit a draft descarga.
     */
    public function updateRecord(Request $request, string $id)
    {
        if (!Auth::can('energy-ops update energy-unload')) {
            return response()->error('You do not have permission to update descargas.', 403);
        }

        $unload = $this->findUnload($id);
        if (!$unload) {
            return response()->error('Descarga not found.', 404);
        }

        $formRequest = UpdateUnloadRequest::createFrom($request);
        $formRequest->setContainer(app());
        $input     = $request->input('unload', $request->all());
        $validator = \Illuminate\Support\Facades\Validator::make($input, $formRequest->rules());
        if ($validator->fails()) {
            return response()->error($validator->errors()->all(), 422);
        }

        $unload = app(UnloadService::class)->update($unload, $validator->validated());

        return $this->respondWithUnload($unload);
    }

    /**
     * Proposed descarga for a vehicle (one line per compartment with product on board).
     */
    public function prefill(Request $request)
    {
        $vehicleId = $request->input('vehicle') ?? $request->input('vehicle_uuid');
        $vehicle   = $this->findVehicle((string) $vehicleId);
        if (!$vehicle) {
            return response()->error('Vehicle not found.', 404);
        }

        return response()->json(['prefill' => app(UnloadService::class)->prefill($vehicle, session('company'))]);
    }

    /**
     * Confirm a draft: empties the compartments, closes the load plan and credits the inventory tank.
     */
    public function confirm(Request $request, string $id)
    {
        if (!Auth::can('energy-ops confirm energy-unload')) {
            return response()->error('You do not have permission to confirm descargas.', 403);
        }

        $unload = $this->findUnload($id);
        if (!$unload) {
            return response()->error('Descarga not found.', 404);
        }

        if (!$unload->isDraft()) {
            return response()->error('Descarga is already ' . $unload->status . '.');
        }

        $unload = app(UnloadService::class)->confirm($unload, $request->user()?->uuid);

        return $this->respondWithUnload($unload);
    }

    /**
     * Void a descarga, reversing its effect on compartments and the inventory tank.
     */
    public function void(Request $request, string $id)
    {
        if (!Auth::can('energy-ops void energy-unload')) {
            return response()->error('You do not have permission to void descargas.', 403);
        }

        $unload = $this->findUnload($id);
        if (!$unload) {
            return response()->error('Descarga not found.', 404);
        }

        if ($unload->status === Unload::STATUS_VOIDED) {
            return response()->error('Descarga is already voided.');
        }

        $unload = app(UnloadService::class)->void($unload, $request->input('reason'), $request->user()?->uuid);

        return $this->respondWithUnload($unload);
    }

    /**
     * Recent descargas for a vehicle.
     */
    public function forVehicle(Request $request, string $vehicleId)
    {
        $vehicle = $this->findVehicle($vehicleId);
        if (!$vehicle) {
            return response()->error('Vehicle not found.', 404);
        }

        $unloads = Unload::query()
            ->where('company_uuid', session('company'))
            ->where('vehicle_uuid', $vehicle->uuid)
            ->with(['inventoryPlace', 'lines.compartment', 'lines.product', 'files'])
            ->latest()
            ->limit((int) $request->input('limit', 20))
            ->get();

        UnloadResource::wrap('unloads');

        return UnloadResource::collection($unloads);
    }

    /**
     * Descargas linked to a load plan.
     */
    public function forLoadPlan(string $loadPlanId)
    {
        $unloads = Unload::query()
            ->where('company_uuid', session('company'))
            ->whereHas('loadPlan', fn ($q) => $q->where('uuid', $loadPlanId)->orWhere('public_id', $loadPlanId))
            ->with(['inventoryPlace', 'lines.compartment', 'lines.product', 'files'])
            ->latest()
            ->get();

        UnloadResource::wrap('unloads');

        return UnloadResource::collection($unloads);
    }

    protected function findUnload(string $id): ?Unload
    {
        return Unload::query()
            ->where('company_uuid', session('company'))
            ->where(fn ($q) => $q->where('uuid', $id)->orWhere('public_id', $id))
            ->first();
    }

    protected function findVehicle(string $id): ?Vehicle
    {
        if ($id === '') {
            return null;
        }

        return Vehicle::query()
            ->where('company_uuid', session('company'))
            ->where(fn ($q) => $q->where('uuid', $id)->orWhere('public_id', $id))
            ->first();
    }

    protected function respondWithUnload(Unload $unload)
    {
        UnloadResource::wrap('unload');

        return new UnloadResource($unload->fresh($this->unloadRelations));
    }
}
