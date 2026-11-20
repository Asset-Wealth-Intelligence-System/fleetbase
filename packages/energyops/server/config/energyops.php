<?php

return [
    'enabled' => env('ENERGYOPS_ENABLED', true),

    'api' => [
        'routing' => [
            'prefix'          => 'energyops',
            'internal_prefix' => 'int',
            'version_prefix'  => 'v1',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety constraints
    |--------------------------------------------------------------------------
    |
    | Defaults for every company; each company can override them from the
    | Energy Ops settings page. Axle-balance and 20/80 surge limits are
    | evaluated by the packer only when enabled.
    |
    */
    'safety' => [
        'axle_balance'      => env('ENERGYOPS_AXLE_BALANCE', false),
        'surge_band'        => env('ENERGYOPS_SURGE_BAND', false),
        'min_fill_ratio'    => 0.2,
        'max_fill_ratio'    => 0.8,
        'require_load_plan' => env('ENERGYOPS_REQUIRE_LOAD_PLAN', true),
        // confirm-load needs at least one attachment (factura / boleta de carga / foto)
        'require_load_proof' => env('ENERGYOPS_REQUIRE_LOAD_PROOF', true),
        // confirm-unload (descarga) needs at least one attachment (foto / firma)
        'require_unload_proof' => env('ENERGYOPS_REQUIRE_UNLOAD_PROOF', true),
    ],

    /*
    | Variance ratio (0-1) between planned and metered volume above which a ticket is
    | flagged as a short fill and the order is marked for re-planning.
    */
    'variance_repack_threshold' => (float) env('ENERGYOPS_VARIANCE_THRESHOLD', 0.15),
];
