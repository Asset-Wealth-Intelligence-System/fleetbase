<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\FleetOps\Models\Asset;
use Fleetbase\FleetOps\Models\Place;
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
use Illuminate\Support\Collection;

/**
 * Energy configuration for a Fleet-Ops vehicle: operation, tractor/trailer coupling, meter.
 * Compartments live on the vehicle, or on the coupled trailer when `is_tractor` is set.
 */
class VehicleProfile extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    protected $table = 'energyops_vehicle_profiles';
    protected $publicIdType = 'evp';
    protected $payloadKey = 'vehicle_profile';
    protected $searchableColumns = ['public_id', 'meter_serial'];
    protected $filterParams = ['vehicle_uuid', 'vehicle', 'operation_uuid', 'operation', 'coupled_asset_uuid', 'default_depot_place_uuid', 'is_tractor', 'status'];

    protected $attributes = [
        'is_tractor' => false,
        'has_meter'  => true,
        'status'     => 'active',
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'vehicle_uuid',
        'operation_uuid',
        'coupled_asset_uuid',
        'default_depot_place_uuid',
        'is_tractor',
        'max_payload_kg',
        'pump_rate_lpm',
        'has_meter',
        'meter_serial',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta'           => Json::class,
        'is_tractor'     => 'boolean',
        'has_meter'      => 'boolean',
        'max_payload_kg' => 'float',
        'pump_rate_lpm'  => 'float',
    ];

    protected $with = ['vehicle', 'operation', 'coupledAsset', 'defaultDepot'];

    protected $appends = ['compartments_count', 'total_capacity'];

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

    public function coupledAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'coupled_asset_uuid', 'uuid');
    }

    /**
     * Habitual loading station for this vehicle (not restrictive: a load plan may pick another depot).
     */
    public function defaultDepot(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'default_depot_place_uuid', 'uuid');
    }

    public function loadPlans(): HasMany
    {
        return $this->hasMany(LoadPlan::class, 'vehicle_uuid', 'vehicle_uuid');
    }

    public function scopeVehicle($query, $value)
    {
        return $query->whereHas('vehicle', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    public function scopeOperation($query, $value)
    {
        return $query->whereHas('operation', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    /**
     * Owner (type, uuid) whose compartments this profile drives.
     */
    public function compartmentOwner(): array
    {
        if ($this->is_tractor && $this->coupled_asset_uuid) {
            return [Compartment::OWNER_ASSET, $this->coupled_asset_uuid];
        }

        return [Compartment::OWNER_VEHICLE, $this->vehicle_uuid];
    }

    public function compartments(): Collection
    {
        [$type, $uuid] = $this->compartmentOwner();
        if (!$uuid) {
            return collect();
        }

        return Compartment::forOwner($type, $uuid)->where('status', 'active')->orderBy('sequence')->get();
    }

    public function getCompartmentsCountAttribute(): int
    {
        [$type, $uuid] = $this->compartmentOwner();

        return $uuid ? Compartment::forOwner($type, $uuid)->count() : 0;
    }

    public function getTotalCapacityAttribute(): float
    {
        [$type, $uuid] = $this->compartmentOwner();

        return $uuid ? (float) Compartment::forOwner($type, $uuid)->sum('capacity_volume') : 0.0;
    }
}
