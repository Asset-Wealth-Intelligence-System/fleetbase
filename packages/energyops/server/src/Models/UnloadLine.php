<?php

namespace Fleetbase\EnergyOps\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Models\Model;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasMetaAttributes;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One compartment on a DESCARGA: what was on board, what was pumped out and what stayed.
 * Volumes are in the compartment's native unit (`unit`).
 */
class UnloadLine extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use TracksApiCredential;
    use SoftDeletes;

    protected $table = 'energyops_unload_lines';
    protected $publicIdType = 'eul';
    protected $payloadKey = 'unload_line';
    protected $filterParams = ['unload_uuid', 'compartment_uuid', 'product_uuid'];

    protected $attributes = [
        'before_volume'    => 0,
        'unloaded_volume'  => 0,
        'remaining_volume' => 0,
        'unit'             => 'gal',
        'sequence'         => 0,
    ];

    protected $fillable = [
        '_key',
        'public_id',
        'company_uuid',
        'unload_uuid',
        'compartment_uuid',
        'product_uuid',
        'before_volume',
        'unloaded_volume',
        'remaining_volume',
        'unit',
        'sequence',
        'meta',
    ];

    protected $casts = [
        'meta'             => Json::class,
        'before_volume'    => 'float',
        'unloaded_volume'  => 'float',
        'remaining_volume' => 'float',
        'sequence'         => 'integer',
    ];

    protected $with = ['compartment', 'product'];

    public function unload(): BelongsTo
    {
        return $this->belongsTo(Unload::class, 'unload_uuid', 'uuid');
    }

    public function compartment(): BelongsTo
    {
        return $this->belongsTo(Compartment::class, 'compartment_uuid', 'uuid');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }
}
