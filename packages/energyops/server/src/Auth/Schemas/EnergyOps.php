<?php

namespace Fleetbase\EnergyOps\Auth\Schemas;

class EnergyOps
{
    /**
     * Must match the header menu item id (`Energy Ops` → `energy-ops`).
     */
    public string $name = 'energy-ops';

    public string $policyName = 'EnergyOps';

    public array $guards = ['sanctum'];

    public array $resources = [
        [
            'name'    => 'product',
            'actions' => ['export', 'import'],
        ],
        [
            'name'    => 'operation',
            'actions' => [],
        ],
        [
            'name'    => 'compartment',
            'actions' => [],
        ],
        [
            'name'    => 'vehicle-profile',
            'actions' => [],
        ],
        [
            'name'    => 'load-plan',
            'actions' => ['pack', 'confirm', 'reload'],
        ],
        [
            'name'    => 'delivery-ticket',
            'actions' => ['reject', 'void'],
        ],
        [
            'name'    => 'unload',
            'actions' => ['confirm', 'void'],
        ],
        [
            'name'    => 'customer-tank',
            'actions' => [],
        ],
        [
            'name'    => 'compatibility-rule',
            'actions' => [],
        ],
        [
            'name'    => 'analytics',
            'actions' => [],
        ],
        [
            'name'    => 'setting',
            'actions' => [],
        ],
    ];

    public array $policies = [
        [
            'name'        => 'EnergyOpsManager',
            'description' => 'Policy for managing Energy Ops products, compartments, load plans, and tickets.',
            'permissions' => [
                'see extension',
                '* product',
                '* operation',
                '* compartment',
                '* vehicle-profile',
                '* load-plan',
                '* delivery-ticket',
                '* unload',
                '* customer-tank',
                '* compatibility-rule',
                '* analytics',
                '* setting',
            ],
        ],
        [
            'name'        => 'EnergyOpsDispatcher',
            'description' => 'Policy for dispatchers: read the catalog and fleet, manage load plans and delivery tickets.',
            'permissions' => [
                'see extension',
                'see product',
                'list product',
                'view product',
                'see operation',
                'list operation',
                'view operation',
                'see compartment',
                'list compartment',
                'view compartment',
                'update compartment',
                'see vehicle-profile',
                'list vehicle-profile',
                'view vehicle-profile',
                'see customer-tank',
                'list customer-tank',
                'view customer-tank',
                'update customer-tank',
                'see compatibility-rule',
                'list compatibility-rule',
                'view compatibility-rule',
                '* load-plan',
                '* delivery-ticket',
                '* unload',
                'see analytics',
                'view analytics',
            ],
        ],
        [
            'name'        => 'EnergyOpsReadOnly',
            'description' => 'Policy for read-only access to Energy Ops.',
            'permissions' => [
                'see extension',
                'see product',
                'list product',
                'view product',
                'see operation',
                'list operation',
                'view operation',
                'see compartment',
                'list compartment',
                'view compartment',
                'see vehicle-profile',
                'list vehicle-profile',
                'view vehicle-profile',
                'see load-plan',
                'list load-plan',
                'view load-plan',
                'see delivery-ticket',
                'list delivery-ticket',
                'view delivery-ticket',
                'see unload',
                'list unload',
                'view unload',
                'see customer-tank',
                'list customer-tank',
                'view customer-tank',
                'see compatibility-rule',
                'list compatibility-rule',
                'view compatibility-rule',
                'see analytics',
                'view analytics',
                'see setting',
                'view setting',
            ],
        ],
    ];

    public array $roles = [
        [
            'name'        => 'Energy Ops Administrator',
            'description' => 'Full access to Energy Ops compartment dispatch.',
            'policies'    => [
                'EnergyOpsManager',
            ],
        ],
        [
            'name'        => 'Energy Ops Dispatcher',
            'description' => 'Packs load plans, confirms loads and issues delivery tickets.',
            'policies'    => [
                'EnergyOpsDispatcher',
            ],
        ],
        [
            'name'        => 'Energy Ops Viewer',
            'description' => 'Read-only access to Energy Ops.',
            'policies'    => [
                'EnergyOpsReadOnly',
            ],
        ],
    ];
}
