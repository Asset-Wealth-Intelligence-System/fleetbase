<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Manifest;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\Models\File;
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
 * DESCARGA — the end-of-day return of whatever is left in the compartments to an inventory site.
 *
 * `unloaded_at` is the physical moment the product was pumped out; `created_at` is when the record
 * reached the platform (they differ when the descarga is captured manually after the fact).
 */
class Unload extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_VOIDED    = 'voided';
    public const STATUSES         = [self::STATUS_DRAFT, self::STATUS_CONFIRMED, self::STATUS_VOIDED];

    public const SOURCE_CONSOLE   = 'console';
    public const SOURCE_NAVIGATOR = 'navigator';
    public const SOURCE_API       = 'api';
    public const SOURCES          = [self::SOURCE_CONSOLE, self::SOURCE_NAVIGATOR, self::SOURCE_API];

    /** File `type` values accepted as proof of unload. */
    public const PROOF_FILE_TYPES = ['unload_photo', 'unload_signature', 'unload_document'];

    protected $table = 'energyops_unloads';
    protected $publicIdType = 'eun';
    protected $payloadKey = 'unload';
    protected $searchableColumns = ['public_id', 'status', 'notes'];
    protected $filterParams = ['vehicle_uuid', 'vehicle', 'driver_uuid', 'load_plan_uuid', 'load_plan', 'manifest_uuid', 'inventory_place_uuid', 'inventory_place', 'status', 'source'];

    protected $attributes = [
        'status'                 => self::STATUS_DRAFT,
        'source'                 => self::SOURCE_CONSOLE,
        'unit'                   => 'gal',
        'total_before_volume'    => 0,
        'total_unloaded_volume'  => 0,
        'total_remaining_volume' => 0,
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'recorded_by_uuid',
        'vehicle_uuid',
        'driver_uuid',
        'load_plan_uuid',
        'manifest_uuid',
        'inventory_place_uuid',
        'inventory_tank_uuid',
        'unit',
        'total_before_volume',
        'total_unloaded_volume',
        'total_remaining_volume',
        'source',
        'status',
        'unloaded_at',
        'confirmed_at',
        'voided_at',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta'                   => Json::class,
        'total_before_volume'    => 'float',
        'total_unloaded_volume'  => 'float',
        'total_remaining_volume' => 'float',
        'unloaded_at'            => 'datetime',
        'confirmed_at'           => 'datetime',
        'voided_at'              => 'datetime',
    ];

    protected $with = ['vehicle', 'inventoryPlace', 'lines', 'files'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_uuid', 'uuid');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_uuid', 'uuid');
    }

    public function loadPlan(): BelongsTo
    {
        return $this->belongsTo(LoadPlan::class, 'load_plan_uuid', 'uuid');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class, 'manifest_uuid', 'uuid');
    }

    public function inventoryPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'inventory_place_uuid', 'uuid');
    }

    public function inventoryTank(): BelongsTo
    {
        return $this->belongsTo(CustomerTank::class, 'inventory_tank_uuid', 'uuid');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(UnloadLine::class, 'unload_uuid', 'uuid')->orderBy('sequence');
    }

    /**
     * Proof of unload: photos / signature images uploaded with `subject_type: energyops:unload`.
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'subject_uuid', 'uuid')->latest();
    }

    public function scopeVehicle($query, $value)
    {
        return $query->whereHas('vehicle', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeLoadPlan($query, $value)
    {
        return $query->whereHas('loadPlan', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeInventoryPlace($query, $value)
    {
        return $query->whereHas('inventoryPlace', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function isDraft(): bool
    {
        return $this->status === static::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === static::STATUS_CONFIRMED;
    }

    /**
     * Recompute denormalised totals from the lines (all lines share the unload unit).
     */
    public function refreshTotals(): self
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        $this->total_before_volume    = round((float) $lines->sum('before_volume'), 3);
        $this->total_unloaded_volume  = round((float) $lines->sum('unloaded_volume'), 3);
        $this->total_remaining_volume = round((float) $lines->sum('remaining_volume'), 3);

        return $this;
    }
}
