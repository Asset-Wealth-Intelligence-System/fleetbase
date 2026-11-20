<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
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
 * A bulk energy product (propane, diesel, kerosene...) belonging to an operation.
 */
class Product extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    protected $table = 'energyops_products';
    protected $publicIdType = 'eprd';
    protected $payloadKey = 'product';
    protected $searchableColumns = ['code', 'name', 'un_number', 'public_id'];
    protected $filterParams = ['operation_uuid', 'operation', 'code', 'active', 'status', 'hazmat_class', 'family'];

    protected $attributes = [
        'default_unit' => 'L',
        'active'       => true,
        'status'       => 'active',
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'operation_uuid',
        'code',
        'name',
        'description',
        'density',
        'un_number',
        'hazmat_class',
        'default_unit',
        'skill',
        'color',
        'active',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta'    => Json::class,
        'density' => 'float',
        'active'  => 'boolean',
    ];

    protected $with = ['operation'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'operation_uuid', 'uuid');
    }

    public function customerTanks(): HasMany
    {
        return $this->hasMany(CustomerTank::class, 'product_uuid', 'uuid');
    }

    public function dedicatedCompartments(): HasMany
    {
        return $this->hasMany(Compartment::class, 'dedicated_product_uuid', 'uuid');
    }

    public function compatibilityRulesFrom(): HasMany
    {
        return $this->hasMany(CompatibilityRule::class, 'from_product_uuid', 'uuid');
    }

    public function compatibilityRulesTo(): HasMany
    {
        return $this->hasMany(CompatibilityRule::class, 'to_product_uuid', 'uuid');
    }

    /**
     * Filter by operation public id or uuid (`?operation=`).
     */
    public function scopeOperation($query, $value)
    {
        return $query->whereHas('operation', fn ($q) => $q->where('uuid', $value)->orWhere('public_id', $value));
    }

    /**
     * Family shortcut from the parent operation (glp / wop / ...).
     */
    public function getFamilyAttribute(): ?string
    {
        return $this->operation?->family ?? $this->getMeta('family');
    }
}
