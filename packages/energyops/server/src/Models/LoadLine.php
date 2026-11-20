<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\FleetOps\Models\Entity;
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
 * One compartment fill on a load plan (product + planned/loaded volume) for an order line.
 */
class LoadLine extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    protected $table = 'energyops_load_lines';
    protected $publicIdType = 'ell';
    protected $payloadKey = 'load_line';
    protected $searchableColumns = ['public_id'];
    protected $filterParams = ['load_plan_uuid', 'order_uuid', 'product_uuid', 'compartment_uuid', 'entity_uuid'];

    protected $attributes = [
        'planned_volume' => 0,
        'sequence'       => 1,
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'load_plan_uuid',
        'compartment_uuid',
        'product_uuid',
        'order_uuid',
        'entity_uuid',
        'planned_volume',
        'loaded_volume',
        'sequence',
        'meta',
    ];

    protected $casts = [
        'meta'           => Json::class,
        'planned_volume' => 'float',
        'loaded_volume'  => 'float',
        'sequence'       => 'integer',
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

    public function compartment(): BelongsTo
    {
        return $this->belongsTo(Compartment::class, 'compartment_uuid', 'uuid');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_uuid', 'uuid');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_uuid', 'uuid');
    }

    public function draws(): HasMany
    {
        return $this->hasMany(StopDraw::class, 'load_line_uuid', 'uuid');
    }

    public function variance(): ?float
    {
        return $this->loaded_volume === null ? null : round((float) $this->loaded_volume - (float) $this->planned_volume, 3);
    }
}
