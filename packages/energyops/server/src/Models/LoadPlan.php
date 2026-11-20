<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\FleetOps\Models\Manifest;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\Models\File;
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
 * A packed trip for one vehicle: which product goes in which compartment, for which orders.
 */
class LoadPlan extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_PACKED     = 'packed';
    public const STATUS_LOADED     = 'loaded';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_CLOSED     = 'closed';
    public const STATUS_CANCELED   = 'canceled';
    public const OPEN_STATUSES     = [self::STATUS_DRAFT, self::STATUS_PACKED, self::STATUS_LOADED, self::STATUS_IN_TRANSIT];

    protected $table = 'energyops_load_plans';
    protected $publicIdType = 'elp';
    protected $payloadKey = 'load_plan';
    protected $searchableColumns = ['public_id', 'status'];
    protected $filterParams = ['vehicle_uuid', 'vehicle', 'operation_uuid', 'operation', 'manifest_uuid', 'depot_place_uuid', 'status', 'order'];

    protected $attributes = [
        'status'               => self::STATUS_DRAFT,
        'total_planned_volume' => 0,
        'orders_count'         => 0,
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'vehicle_uuid',
        'operation_uuid',
        'manifest_uuid',
        'depot_place_uuid',
        'status',
        'total_planned_volume',
        'total_loaded_volume',
        'orders_count',
        'packed_at',
        'loaded_at',
        'closed_at',
        'meta',
    ];

    protected $casts = [
        'meta'                 => Json::class,
        'total_planned_volume' => 'float',
        'total_loaded_volume'  => 'float',
        'orders_count'         => 'integer',
        'packed_at'            => 'datetime',
        'loaded_at'            => 'datetime',
        'closed_at'            => 'datetime',
    ];

    protected $with = ['vehicle', 'operation', 'depot', 'lines', 'draws', 'tickets', 'files'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'operation_uuid', 'uuid');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class, 'manifest_uuid', 'uuid');
    }

    /**
     * Depot (place of type `depot`) where this trip was / will be loaded.
     */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'depot_place_uuid', 'uuid');
    }

    /**
     * Comprobante de carga: invoice, loading ticket and photos uploaded with `subject_type: energyops:load-plan`.
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'subject_uuid', 'uuid')->latest();
    }

    public function unloads(): HasMany
    {
        return $this->hasMany(Unload::class, 'load_plan_uuid', 'uuid');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LoadLine::class, 'load_plan_uuid', 'uuid')->orderBy('sequence');
    }

    public function draws(): HasMany
    {
        return $this->hasMany(StopDraw::class, 'load_plan_uuid', 'uuid')->orderBy('sequence');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(DeliveryTicket::class, 'load_plan_uuid', 'uuid');
    }

    public function scopeVehicle($query, $value)
    {
        return $query->whereHas('vehicle', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeOperation($query, $value)
    {
        return $query->whereHas('operation', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeOrder($query, $value)
    {
        return $query->whereHas('lines.order', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', static::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, static::OPEN_STATUSES, true);
    }

    /**
     * Whether new pedidos may still be drawn from the product already on board.
     */
    public function isOnBoard(): bool
    {
        return in_array($this->status, [static::STATUS_LOADED, static::STATUS_IN_TRANSIT], true);
    }

    /**
     * Proof-of-load details entered when the load was confirmed (`meta.load_proof`).
     */
    public function loadProof(): array
    {
        $proof = $this->getMeta('load_proof');

        return is_array($proof) ? $proof : [];
    }

    /**
     * Recompute denormalised totals from the lines.
     */
    public function refreshTotals(): self
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        $this->total_planned_volume = round((float) $lines->sum('planned_volume'), 3);
        $loaded                     = $lines->whereNotNull('loaded_volume');
        $this->total_loaded_volume  = $loaded->isEmpty() ? null : round((float) $loaded->sum('loaded_volume'), 3);
        $this->orders_count         = $lines->pluck('order_uuid')->filter()->unique()->count();

        return $this;
    }
}
