<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Asset;
use Fleetbase\FleetOps\Models\Vehicle;
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
 * A tank compartment mounted on a Fleet-Ops vehicle (tanker / bobtail) or asset (trailer).
 *
 * `owner_type` is limited to `vehicle` | `asset`; the matching `vehicle_uuid` / `asset_uuid`
 * FK column is kept in sync so database-level referential integrity holds.
 */
class Compartment extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    public const OWNER_VEHICLE = 'vehicle';
    public const OWNER_ASSET   = 'asset';
    public const OWNER_TYPES   = [self::OWNER_VEHICLE, self::OWNER_ASSET];

    protected $table = 'energyops_compartments';
    protected $publicIdType = 'ecm';
    protected $payloadKey = 'compartment';
    protected $searchableColumns = ['code', 'name', 'public_id'];
    protected $filterParams = ['owner_uuid', 'owner_type', 'vehicle_uuid', 'asset_uuid', 'status', 'dedicated_product_uuid', 'last_product_uuid'];

    protected $attributes = [
        'owner_type'       => self::OWNER_VEHICLE,
        'sequence'         => 1,
        'capacity_volume'  => 0,
        'unit'             => 'L',
        'max_fill_percent' => 85,
        'residual_volume'  => 0,
        'requires_flush'   => false,
        'status'           => 'active',
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'owner_type',
        'owner_uuid',
        'vehicle_uuid',
        'asset_uuid',
        'code',
        'name',
        'sequence',
        'capacity_volume',
        'unit',
        'max_fill_percent',
        'dedicated_product_uuid',
        'last_product_uuid',
        'residual_volume',
        'requires_flush',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta'             => Json::class,
        'capacity_volume'  => 'float',
        'max_fill_percent' => 'float',
        'residual_volume'  => 'float',
        'requires_flush'   => 'boolean',
        'sequence'         => 'integer',
    ];

    protected $with = ['dedicatedProduct', 'lastProduct'];

    protected $appends = ['safe_capacity', 'available_volume', 'remaining_capacity', 'fill_percent'];

    protected static function boot()
    {
        parent::boot();

        // Keep the typed FK columns aligned with the polymorphic owner columns.
        static::saving(function (Compartment $compartment) {
            // Accept either the polymorphic pair (owner_type/owner_uuid) or a typed FK (vehicle_uuid/asset_uuid)
            if (!in_array($compartment->owner_type, static::OWNER_TYPES, true)) {
                $compartment->owner_type = $compartment->asset_uuid && !$compartment->vehicle_uuid ? static::OWNER_ASSET : static::OWNER_VEHICLE;
            }

            if ($compartment->owner_type === static::OWNER_ASSET) {
                $compartment->owner_uuid   = $compartment->owner_uuid ?: $compartment->asset_uuid;
                $compartment->asset_uuid   = $compartment->owner_uuid;
                $compartment->vehicle_uuid = null;
            } else {
                $compartment->owner_uuid   = $compartment->owner_uuid ?: $compartment->vehicle_uuid;
                $compartment->vehicle_uuid = $compartment->owner_uuid;
                $compartment->asset_uuid   = null;
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_uuid', 'uuid');
    }

    public function dedicatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'dedicated_product_uuid', 'uuid');
    }

    public function lastProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'last_product_uuid', 'uuid');
    }

    public function loadLines(): HasMany
    {
        return $this->hasMany(LoadLine::class, 'compartment_uuid', 'uuid');
    }

    /**
     * The owning Fleet-Ops record (vehicle or asset).
     */
    public function owner(): ?Model
    {
        return $this->owner_type === static::OWNER_ASSET ? $this->asset : $this->vehicle;
    }

    public function getOwnerAttribute(): ?array
    {
        $owner = $this->owner();
        if (!$owner) {
            return null;
        }

        return [
            'uuid'         => $owner->uuid,
            'public_id'    => $owner->public_id,
            'type'         => $this->owner_type,
            'display_name' => $owner->display_name ?? $owner->name ?? $owner->plate_number ?? null,
            'plate_number' => $owner->plate_number ?? null,
        ];
    }

    public function safeCapacity(): float
    {
        return round(((float) $this->capacity_volume) * (min(100, max(0, (float) $this->max_fill_percent)) / 100), 3);
    }

    public function getSafeCapacityAttribute(): float
    {
        return $this->safeCapacity();
    }

    /**
     * Volume that can still be loaded without exceeding the safe fill.
     */
    public function availableVolume(): float
    {
        return max(0, $this->safeCapacity() - (float) $this->residual_volume);
    }

    public function getAvailableVolumeAttribute(): float
    {
        return $this->availableVolume();
    }

    /**
     * @deprecated use availableVolume(); kept for the packer.
     */
    public function remainingCapacity(): float
    {
        return $this->availableVolume();
    }

    /**
     * Unit-aware helpers: compartments are stored in their native unit (`unit`, e.g. gal),
     * while the packer / load plans / tickets work in liters.
     */
    public function toLiters(float $qty): float
    {
        return EnergyOps::toLiters($qty, (string) ($this->unit ?: 'L'));
    }

    public function fromLiters(float $liters): float
    {
        return EnergyOps::fromLiters($liters, (string) ($this->unit ?: 'L'));
    }

    public function capacityLiters(): float
    {
        return $this->toLiters((float) $this->capacity_volume);
    }

    public function availableVolumeLiters(): float
    {
        return $this->toLiters($this->availableVolume());
    }

    public function residualVolumeLiters(): float
    {
        return $this->toLiters((float) $this->residual_volume);
    }

    public function getRemainingCapacityAttribute(): float
    {
        return $this->remainingCapacity();
    }

    public function getFillPercentAttribute(): float
    {
        if ((float) $this->capacity_volume <= 0) {
            return 0;
        }

        return round(((float) $this->residual_volume / (float) $this->capacity_volume) * 100, 1);
    }

    public function scopeForOwner($query, string $ownerType, string $ownerUuid)
    {
        return $query->where('owner_type', $ownerType)->where('owner_uuid', $ownerUuid);
    }

    /**
     * Clear residual product after a flush.
     */
    public function flush(): self
    {
        $this->residual_volume  = 0;
        $this->last_product_uuid = null;
        $this->requires_flush   = false;
        $this->save();

        return $this;
    }
}
