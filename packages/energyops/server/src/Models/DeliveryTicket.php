<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\Models\Model;
use Fleetbase\Models\User;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasMetaAttributes;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\Searchable;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Metered delivery at a stop. Issuing a ticket draws down the compartment residual and
 * tops up the customer tank; voiding reverses both.
 */
class DeliveryTicket extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    public const STATUS_ISSUED         = 'issued';
    public const STATUS_VOID           = 'void';
    public const EXCEPTION_SHORT_FILL  = 'short_fill';
    public const EXCEPTION_REJECT      = 'reject';
    public const EXCEPTION_CONTAMINATE = 'contamination';
    public const EXCEPTION_TYPES       = [self::EXCEPTION_SHORT_FILL, self::EXCEPTION_REJECT, self::EXCEPTION_CONTAMINATE];

    protected $table = 'energyops_delivery_tickets';
    protected $publicIdType = 'etk';
    protected $payloadKey = 'delivery_ticket';
    protected $searchableColumns = ['public_id', 'variance_reason', 'seal_in', 'seal_out', 'invoice_number'];
    protected $filterParams = ['order_uuid', 'order', 'load_plan_uuid', 'load_plan', 'product_uuid', 'product', 'compartment_uuid', 'customer_tank_uuid', 'status', 'exception_type'];

    protected $attributes = [
        'status'      => self::STATUS_ISSUED,
        'metered_qty' => 0,
        'unit'        => 'L',
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'order_uuid',
        'load_plan_uuid',
        'stop_draw_uuid',
        'compartment_uuid',
        'product_uuid',
        'customer_tank_uuid',
        'metered_qty',
        'planned_qty',
        'variance_qty',
        'variance_reason',
        'unit',
        'seal_in',
        'seal_out',
        'invoice_number',
        'meter_start',
        'meter_end',
        'exception_type',
        'status',
        'issued_at',
        'meta',
    ];

    protected $casts = [
        'meta'         => Json::class,
        'metered_qty'  => 'float',
        'planned_qty'  => 'float',
        'variance_qty' => 'float',
        'issued_at'    => 'datetime',
    ];

    protected $with = ['order', 'compartment', 'product', 'customerTank'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_uuid', 'uuid');
    }

    public function loadPlan(): BelongsTo
    {
        return $this->belongsTo(LoadPlan::class, 'load_plan_uuid', 'uuid');
    }

    public function stopDraw(): BelongsTo
    {
        return $this->belongsTo(StopDraw::class, 'stop_draw_uuid', 'uuid');
    }

    public function compartment(): BelongsTo
    {
        return $this->belongsTo(Compartment::class, 'compartment_uuid', 'uuid');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    public function customerTank(): BelongsTo
    {
        return $this->belongsTo(CustomerTank::class, 'customer_tank_uuid', 'uuid');
    }

    public function scopeOrder($query, $value)
    {
        return $query->whereHas('order', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeLoadPlan($query, $value)
    {
        return $query->whereHas('loadPlan', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeProduct($query, $value)
    {
        return $query->whereHas('product', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    /**
     * `HasApiModelBehavior::createRecordFromRequest` calls this method instead of `static::create`
     * so tickets created from the console go through the issuing workflow (residual + tank updates).
     */
    protected string $creationMethod = 'issueFromInput';

    public function issueFromInput(array $input): self
    {
        return app(\Fleetbase\EnergyOps\Services\TicketService::class)->issue($input);
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function hasException(): bool
    {
        return !empty($this->exception_type);
    }

    public function variancePercent(): ?float
    {
        $planned = (float) $this->planned_qty;
        if ($planned <= 0) {
            return null;
        }

        return round(((float) $this->variance_qty / $planned) * 100, 1);
    }
}
