<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Resources\v1\LoadPlan as LoadPlanResource;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Services\CompartmentPacker;
use Fleetbase\EnergyOps\Services\LoadPlanService;
use Fleetbase\EnergyOps\Services\ReloadService;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Http\Request;

class LoadPlanController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'load-plan';

    /** Relations embedded in every single-plan response. */
    protected array $planRelations = ['vehicle', 'operation', 'depot', 'files', 'lines.compartment', 'lines.product', 'draws.compartment', 'draws.product', 'tickets.product', 'tickets.compartment'];

    /**
     * Plant confirms actual loaded volumes for a packed plan.
     *
     * Payload: { actuals: { [line uuid|public_id]: volume }, depot_place_uuid?, proof?: { invoice_number, ticket_number, notes } }
     */
    public function confirmLoad(Request $request, string $id)
    {
        $plan = $this->findPlan($id);
        if (!$plan) {
            return response()->error('Load plan not found.', 404);
        }

        if (!in_array($plan->status, ['draft', 'packed'], true)) {
            return response()->error('Only packed load plans can be confirmed.');
        }

        $request->validate([
            'actuals'              => 'nullable|array',
            'depot_place_uuid'     => 'nullable|string',
            'proof'                => 'nullable|array',
            'proof.invoice_number' => 'nullable|string|max:64',
            'proof.ticket_number'  => 'nullable|string|max:64',
            'proof.notes'          => 'nullable|string|max:2000',
        ]);

        $plan = app(LoadPlanService::class)->confirmLoad($plan, (array) $request->input('actuals', []), [
            'depot_place_uuid' => $request->input('depot_place_uuid'),
            'proof'            => (array) $request->input('proof', []),
        ]);

        return $this->respondWithPlan($plan);
    }

    /**
     * Add late pedidos to a truck that is already loaded, drawing from the product on board.
     *
     * Payload: { order_ids: [public_id|uuid] }
     */
    public function addOrders(Request $request, string $id)
    {
        $plan = $this->findPlan($id);
        if (!$plan) {
            return response()->error('Load plan not found.', 404);
        }

        if (!$plan->isOnBoard()) {
            return response()->error('Only loaded or in-transit load plans can take additional pedidos.');
        }

        $request->validate(['order_ids' => 'required|array|min:1']);
        $orderIds = (array) $request->input('order_ids', []);

        $orders = Order::query()
            ->where('company_uuid', session('company'))
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereIn('public_id', $orderIds)->orWhereIn('uuid', $orderIds))
            ->with(['payload.entities', 'payload.dropoff', 'orderConfig'])
            ->get();

        if ($orders->isEmpty()) {
            return response()->error('No orders found to add.');
        }

        $result              = app(LoadPlanService::class)->addOrders($plan, $orders, app(CompartmentPacker::class));
        $result['load_plan'] = (new LoadPlanResource($result['load_plan']->load($this->planRelations)))->resolve();

        return response()->json($result);
    }

    /**
     * Uncommitted product on board per compartment (what late pedidos can draw from).
     */
    public function onBoardStock(string $id)
    {
        $plan = $this->findPlan($id);
        if (!$plan) {
            return response()->error('Load plan not found.', 404);
        }

        $stock = collect(app(LoadPlanService::class)->onBoardStock($plan))->map(function ($cell) {
            /** @var \Fleetbase\EnergyOps\Models\Compartment $compartment */
            $compartment = $cell['compartment'];

            $residualLiters = $compartment->residualVolumeLiters();
            $product        = $cell['product_uuid'] ? \Fleetbase\EnergyOps\Models\Product::where('uuid', $cell['product_uuid'])->first(['uuid', 'code', 'name']) : null;

            return [
                'compartment_uuid' => $compartment->uuid,
                'compartment_code' => $compartment->code,
                'compartment_name' => $compartment->code ?? $compartment->name ?? $compartment->public_id,
                'product_uuid'     => $cell['product_uuid'],
                'product_code'     => $product?->code,
                'product_name'     => $product?->name,
                'residual_liters'  => round($residualLiters, 3),
                'committed_liters' => round(max(0, $residualLiters - $cell['available']), 3),
                'available_liters' => $cell['available'],
                'available'        => round($compartment->fromLiters($cell['available']), 3),
                'unit'             => $compartment->unit,
            ];
        })->values();

        return response()->json(['stock' => $stock, 'total_available_liters' => round($stock->sum('available_liters'), 3)]);
    }

    /**
     * Close a plan after the trip is finished.
     */
    public function close(Request $request, string $id)
    {
        $plan = $this->findPlan($id);
        if (!$plan) {
            return response()->error('Load plan not found.', 404);
        }

        if (in_array($plan->status, ['closed', 'canceled'], true)) {
            return response()->error('Load plan is already ' . $plan->status . '.');
        }

        $plan = app(LoadPlanService::class)->close($plan);

        return $this->respondWithPlan($plan);
    }

    /**
     * Cancel a plan that has not been loaded yet and release its orders.
     */
    public function cancel(Request $request, string $id)
    {
        $plan = $this->findPlan($id);
        if (!$plan) {
            return response()->error('Load plan not found.', 404);
        }

        if (!in_array($plan->status, ['draft', 'packed'], true)) {
            return response()->error('Only draft or packed load plans can be canceled.');
        }

        $plan = app(LoadPlanService::class)->cancel($plan, $request->input('reason'));

        return $this->respondWithPlan($plan);
    }

    /**
     * Pack remaining demand onto the plan's vehicle once it returns to plant.
     */
    public function reload(Request $request, string $id)
    {
        $plan = $this->findPlan($id);
        if (!$plan) {
            return response()->error('Load plan not found.', 404);
        }

        $result = app(ReloadService::class)->reload($plan);
        $result['load_plan'] = isset($result['load_plan']) && $result['load_plan'] instanceof LoadPlan
            ? (new LoadPlanResource($result['load_plan']->load($this->planRelations)))->resolve()
            : null;

        return response()->json($result);
    }

    /**
     * Active load plan for a Fleet-Ops order.
     */
    public function forOrder(string $orderId)
    {
        $order = Order::where('company_uuid', session('company'))
            ->where(fn ($q) => $q->where('uuid', $orderId)->orWhere('public_id', $orderId))
            ->first();

        if (!$order) {
            return response()->error('Order not found.', 404);
        }

        $plan = app(LoadPlanService::class)->forOrder($order);
        if (!$plan) {
            return response()->json(['load_plan' => null]);
        }

        return $this->respondWithPlan($plan);
    }

    /**
     * Recent load plans for a vehicle.
     */
    public function forVehicle(Request $request, string $vehicleId)
    {
        $companyUuid = session('company');
        $vehicle     = Vehicle::where('company_uuid', $companyUuid)
            ->where(fn ($q) => $q->where('uuid', $vehicleId)->orWhere('public_id', $vehicleId))
            ->first();

        if (!$vehicle) {
            return response()->error('Vehicle not found.', 404);
        }

        $plans = LoadPlan::query()
            ->where('company_uuid', $companyUuid)
            ->where('vehicle_uuid', $vehicle->uuid)
            ->with(['operation', 'lines.product', 'lines.compartment', 'draws'])
            ->latest()
            ->limit((int) $request->input('limit', 20))
            ->get();

        LoadPlanResource::wrap('load_plans');

        return LoadPlanResource::collection($plans);
    }

    protected function findPlan(string $id): ?LoadPlan
    {
        return LoadPlan::query()
            ->where('company_uuid', session('company'))
            ->where(fn ($q) => $q->where('uuid', $id)->orWhere('public_id', $id))
            ->first();
    }

    protected function respondWithPlan(LoadPlan $plan)
    {
        LoadPlanResource::wrap('load_plan');

        return new LoadPlanResource($plan->fresh($this->planRelations));
    }
}
