<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Api\v1;

use Fleetbase\EnergyOps\Http\Controllers\Api\v1\Concerns\AttachesProofFiles;
use Fleetbase\EnergyOps\Http\Resources\v1\LoadPlan as LoadPlanResource;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Services\CompartmentPacker;
use Fleetbase\EnergyOps\Services\LoadPlanService;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Consumable load plan API for drivers / plant staff (Navigator, integrations).
 */
class LoadPlanController extends ApiResourceController
{
    use AttachesProofFiles;

    protected string $model    = LoadPlan::class;
    protected string $resource = LoadPlanResource::class;
    protected array $with      = ['vehicle', 'operation', 'depot', 'files', 'lines.compartment', 'lines.product', 'draws', 'tickets'];
    protected string $label    = 'Load plan';

    /**
     * Confirm the load from a device: actual volumes, depot and proof numbers.
     */
    public function confirmLoad(string $id, Request $request)
    {
        try {
            /** @var LoadPlan $plan */
            $plan = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError('Load plan resource not found.', 404);
        }

        if (!in_array($plan->status, ['draft', 'packed'], true)) {
            return response()->apiError('Only packed load plans can be confirmed.');
        }

        $plan = app(LoadPlanService::class)->confirmLoad($plan, (array) $request->input('actuals', []), [
            'depot_place_uuid' => $request->input('depot') ?? $request->input('depot_place_uuid'),
            'proof'            => (array) $request->input('proof', []),
        ]);

        return new LoadPlanResource($plan->load($this->with));
    }

    /**
     * Add late pedidos to a loaded truck from the product on board.
     */
    public function addOrders(string $id, Request $request)
    {
        try {
            /** @var LoadPlan $plan */
            $plan = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError('Load plan resource not found.', 404);
        }

        $orderIds = (array) ($request->input('orders') ?? $request->input('order_ids', []));
        $orders   = Order::query()
            ->where('company_uuid', session('company'))
            ->where(fn ($q) => $q->whereIn('public_id', $orderIds)->orWhereIn('uuid', $orderIds))
            ->with(['payload.entities', 'payload.dropoff', 'orderConfig'])
            ->get();

        if ($orders->isEmpty()) {
            return response()->apiError('No orders found to add.');
        }

        $result              = app(LoadPlanService::class)->addOrders($plan, $orders, app(CompartmentPacker::class));
        $result['load_plan'] = (new LoadPlanResource($result['load_plan']->load($this->with)))->resolve();

        return response()->json($result);
    }

    /**
     * Attach a proof of load (factura / boleta de carga / foto).
     */
    public function uploadFile(string $id, Request $request)
    {
        try {
            $plan = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError('Load plan resource not found.', 404);
        }

        return $this->attachProofFile($request, $plan, 'uploads/energyops/load-plans', 'load_photo', ['load_invoice', 'load_ticket', 'load_photo', 'load_document']);
    }
}
