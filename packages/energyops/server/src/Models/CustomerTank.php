<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\FleetOps\Models\Place;
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
 * A customer's storage tank at a Fleet-Ops place. Used for ullage checks and level tracking.
 */
class CustomerTank extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    public const TANK_TYPES = ['aboveground', 'underground', 'cylinder_bank', 'mobile'];

    protected $table = 'energyops_customer_tanks';
    protected $publicIdType = 'ect';
    protected $payloadKey = 'customer_tank';
    protected $searchableColumns = ['name', 'serial_number', 'public_id'];
    protected $filterParams = ['place_uuid', 'place', 'product_uuid', 'product', 'tank_type', 'status'];

    protected $attributes = [
        'tank_type'         => 'aboveground',
        'unit'              => 'L',
        'capacity_volume'   => 0,
        'safe_fill_percent' => 85,
        'last_known_volume' => 0,
        'status'            => 'active',
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'place_uuid',
        'product_uuid',
        'name',
        'serial_number',
        'tank_type',
        'capacity_volume',
        'unit',
        'safe_fill_percent',
        'last_known_volume',
        'reorder_point',
        'last_delivery_at',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta'              => Json::class,
        'capacity_volume'   => 'float',
        'safe_fill_percent' => 'float',
        'last_known_volume' => 'float',
        'reorder_point'     => 'float',
        'last_delivery_at'  => 'datetime',
    ];

    protected $with = ['place', 'product'];

    protected $appends = ['ullage', 'safe_fill_volume', 'fill_percent', 'needs_reorder'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_uuid', 'uuid');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(DeliveryTicket::class, 'customer_tank_uuid', 'uuid');
    }

    public function scopePlace($query, $value)
    {
        return $query->whereHas('place', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeProduct($query, $value)
    {
        return $query->whereHas('product', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function safeFillVolume(): float
    {
        return round(((float) $this->capacity_volume) * (min(100, max(0, (float) $this->safe_fill_percent)) / 100), 3);
    }

    public function getSafeFillVolumeAttribute(): float
    {
        return $this->safeFillVolume();
    }

    /**
     * Room left in the tank up to the safe fill.
     */
    public function ullage(): float
    {
        return max(0, $this->safeFillVolume() - (float) $this->last_known_volume);
    }

    public function getUllageAttribute(): float
    {
        return $this->ullage();
    }

    public function getFillPercentAttribute(): float
    {
        if ((float) $this->capacity_volume <= 0) {
            return 0;
        }

        return round(((float) $this->last_known_volume / (float) $this->capacity_volume) * 100, 1);
    }

    public function getNeedsReorderAttribute(): bool
    {
        return $this->reorder_point !== null && (float) $this->last_known_volume <= (float) $this->reorder_point;
    }

    /**
     * Apply a delivered quantity to the tank level.
     */
    public function receive(float $quantity, ?\DateTimeInterface $at = null): self
    {
        $this->last_known_volume = max(0, (float) $this->last_known_volume + $quantity);
        $this->last_delivery_at  = $at ?? now();
        $this->save();

        return $this;
    }
}
