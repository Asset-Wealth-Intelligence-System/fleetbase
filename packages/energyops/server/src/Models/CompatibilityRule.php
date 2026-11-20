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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product-switch rule: what happens when a compartment that last carried `from` is loaded with `to`.
 * Null `from`/`to` act as wildcards; operation-level rules apply to every product in the operation.
 */
class CompatibilityRule extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use Searchable;
    use SoftDeletes;

    public const ALLOWED        = 'allowed';
    public const FLUSH_REQUIRED = 'flush_required';
    public const FORBIDDEN      = 'forbidden';
    public const ACTIONS        = [self::ALLOWED, self::FLUSH_REQUIRED, self::FORBIDDEN];

    protected $table = 'energyops_compatibility_rules';
    protected $publicIdType = 'ecr';
    protected $payloadKey = 'compatibility_rule';
    protected $searchableColumns = ['action', 'notes', 'public_id'];
    protected $filterParams = ['action', 'from_product_uuid', 'to_product_uuid', 'from_operation_uuid', 'to_operation_uuid', 'status'];

    protected $attributes = [
        'action'           => self::ALLOWED,
        'same_family_only' => true,
        'status'           => 'active',
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'created_by_uuid',
        'from_product_uuid',
        'to_product_uuid',
        'from_operation_uuid',
        'to_operation_uuid',
        'action',
        'same_family_only',
        'flush_volume',
        'notes',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta'             => Json::class,
        'same_family_only' => 'boolean',
        'flush_volume'     => 'float',
    ];

    protected $with = ['fromProduct', 'toProduct', 'fromOperation', 'toOperation'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    public function fromProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'from_product_uuid', 'uuid')->withoutGlobalScopes();
    }

    public function toProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'to_product_uuid', 'uuid')->withoutGlobalScopes();
    }

    public function fromOperation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'from_operation_uuid', 'uuid');
    }

    public function toOperation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'to_operation_uuid', 'uuid');
    }

    public function isForbidden(): bool
    {
        return $this->action === self::FORBIDDEN;
    }

    public function requiresFlush(): bool
    {
        return $this->action === self::FLUSH_REQUIRED;
    }
}
