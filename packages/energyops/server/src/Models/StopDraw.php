<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\FleetOps\Models\ManifestStop;
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Planned draw from a compartment at a stop (one per order line per compartment).
 */
class StopDraw extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    protected $table = 'energyops_stop_draws';
    protected $publicIdType = 'esd';
    protected $payloadKey = 'stop_draw';
    protected $searchableColumns = ['public_id'];
    protected $filterParams = ['load_plan_uuid', 'load_line_uuid', 'order_uuid', 'compartment_uuid', 'product_uuid', 'manifest_stop_uuid'];

    protected $attributes = [
        'planned_qty' => 0,
        'sequence'    => 1,
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'load_plan_uuid',
        'load_line_uuid',
        'order_uuid',
        'manifest_stop_uuid',
        'compartment_uuid',
        'product_uuid',
        'planned_qty',
        'metered_qty',
        'sequence',
        'meta',
    ];

    protected $casts = [
        'meta'        => Json::class,
        'planned_qty' => 'float',
        'metered_qty' => 'float',
        'sequence'    => 'integer',
    ];

    protected $with = ['compartment', 'product', 'order'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function loadPlan(): BelongsTo
    {
        return $this->belongsTo(LoadPlan::class, 'load_plan_uuid', 'uuid');
    }

    public function loadLine(): BelongsTo
    {
        return $this->belongsTo(LoadLine::class, 'load_line_uuid', 'uuid');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_uuid', 'uuid');
    }

    public function manifestStop(): BelongsTo
    {
        return $this->belongsTo(ManifestStop::class, 'manifest_stop_uuid', 'uuid');
    }

    public function compartment(): BelongsTo
    {
        return $this->belongsTo(Compartment::class, 'compartment_uuid', 'uuid');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(DeliveryTicket::class, 'stop_draw_uuid', 'uuid');
    }

    public function isDelivered(): bool
    {
        return $this->metered_qty !== null;
    }
}
