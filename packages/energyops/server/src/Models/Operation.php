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
 * An operation is a bulk product line (LPG / white oil / lubricants) with its depot,
 * default unit and packing policies.
 */
class Operation extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    public const FAMILIES = ['glp', 'wop', 'lubricants', 'other'];

    protected $table = 'energyops_operations';
    protected $publicIdType = 'eop';
    protected $payloadKey = 'operation';
    protected $searchableColumns = ['code', 'name', 'public_id'];
    protected $filterParams = ['code', 'family', 'status', 'unit', 'depot_place_uuid'];

    protected $attributes = [
        'family'                  => 'glp',
        'unit'                    => 'L',
        'allow_split_delivery'    => false,
        'block_over_ullage'       => true,
        'default_service_seconds' => 900,
        'status'                  => 'active',
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'depot_place_uuid',
        'code',
        'name',
        'family',
        'unit',
        'allow_split_delivery',
        'block_over_ullage',
        'default_service_seconds',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta'                    => Json::class,
        'allow_split_delivery'    => 'boolean',
        'block_over_ullage'       => 'boolean',
        'default_service_seconds' => 'integer',
    ];

    protected $appends = ['products_count'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Fleetbase\Models\Company::class, 'company_uuid', 'uuid');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'depot_place_uuid', 'uuid');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'operation_uuid', 'uuid');
    }

    public function vehicleProfiles(): HasMany
    {
        return $this->hasMany(VehicleProfile::class, 'operation_uuid', 'uuid');
    }

    public function loadPlans(): HasMany
    {
        return $this->hasMany(LoadPlan::class, 'operation_uuid', 'uuid');
    }

    public function getProductsCountAttribute(): int
    {
        if (array_key_exists('products_count', $this->attributes)) {
            return (int) $this->attributes['products_count'];
        }

        return $this->relationLoaded('products') ? $this->products->count() : $this->products()->count();
    }
}
