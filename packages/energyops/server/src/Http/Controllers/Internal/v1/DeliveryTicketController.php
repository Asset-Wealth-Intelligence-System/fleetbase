<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Requests\CreateDeliveryTicketRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateDeliveryTicketRequest;
use Fleetbase\EnergyOps\Http\Resources\v1\DeliveryTicket as DeliveryTicketResource;
use Fleetbase\EnergyOps\Models\DeliveryTicket;
use Fleetbase\EnergyOps\Services\TicketService;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryTicketController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'delivery-ticket';

    /**
     * The form request used to validate creation.
     */
    public $createRequest = CreateDeliveryTicketRequest::class;

    /**
     * The form request used to validate updates.
     */
    public $updateRequest = UpdateDeliveryTicketRequest::class;

    /** Relations embedded in every single-ticket response. */
    protected array $ticketRelations = ['order', 'loadPlan', 'stopDraw', 'compartment', 'product', 'customerTank'];

    /**
     * Void an issued ticket, restoring the compartment residual and tank level it affected.
     */
    public function void(Request $request, string $id)
    {
        $ticket = $this->findTicket($id);
        if (!$ticket) {
            return response()->error('Delivery ticket not found.', 404);
        }

        if ($ticket->isVoid()) {
            return response()->error('Delivery ticket is already void.');
        }

        $ticket = app(TicketService::class)->void($ticket, $request->input('reason'), $request->user()?->uuid);

        return $this->respondWithTicket($ticket);
    }

    /**
     * Record a delivery exception (short fill / reject / contamination) on a ticket.
     */
    public function exception(Request $request, string $id)
    {
        $ticket = $this->findTicket($id);
        if (!$ticket) {
            return response()->error('Delivery ticket not found.', 404);
        }

        $request->validate([
            'exception_type' => ['required', Rule::in(DeliveryTicket::EXCEPTION_TYPES)],
            'reason'         => ['nullable', 'string', 'max:500'],
        ]);

        $ticket = app(TicketService::class)->recordException($ticket, $request->input('exception_type'), $request->input('reason'), $request->user()?->uuid);

        return $this->respondWithTicket($ticket);
    }

    /**
     * Backwards compatible alias for `exception` with type `reject`.
     */
    public function reject(Request $request, string $id)
    {
        $request->merge(['exception_type' => 'reject']);

        return $this->exception($request, $id);
    }

    /**
     * Tickets issued against a Fleet-Ops order.
     */
    public function forOrder(string $orderId)
    {
        $companyUuid = session('company');
        $order       = Order::where('company_uuid', $companyUuid)
            ->where(fn ($q) => $q->where('uuid', $orderId)->orWhere('public_id', $orderId))
            ->first();

        if (!$order) {
            return response()->error('Order not found.', 404);
        }

        $tickets = DeliveryTicket::query()
            ->where('company_uuid', $companyUuid)
            ->where('order_uuid', $order->uuid)
            ->with(['compartment', 'product', 'customerTank'])
            ->latest()
            ->get();

        DeliveryTicketResource::wrap('delivery_tickets');

        return DeliveryTicketResource::collection($tickets);
    }

    protected function findTicket(string $id): ?DeliveryTicket
    {
        return DeliveryTicket::query()
            ->where('company_uuid', session('company'))
            ->where(fn ($q) => $q->where('uuid', $id)->orWhere('public_id', $id))
            ->first();
    }

    protected function respondWithTicket(DeliveryTicket $ticket)
    {
        DeliveryTicketResource::wrap('delivery_ticket');

        return new DeliveryTicketResource($ticket->fresh($this->ticketRelations));
    }
}
