<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EnergyOps API Routes
|--------------------------------------------------------------------------
|
| `energyops/v1`     – consumable API (API key auth, `fleetbase.api`)
| `energyops/int/v1` – console API (session auth, `fleetbase.protected`)
|
*/
Route::prefix(config('energyops.api.routing.prefix', 'energyops'))->namespace('Fleetbase\EnergyOps\Http\Controllers')->group(function ($router) {
    /*
    |--------------------------------------------------------------------------
    | Consumable EnergyOps API Routes
    |--------------------------------------------------------------------------
    */
    $router->group(['prefix' => 'v1', 'middleware' => ['fleetbase.api'], 'namespace' => 'Api\v1'], function ($router) {
        foreach (['products' => 'ProductController', 'compartments' => 'CompartmentController', 'customer-tanks' => 'CustomerTankController', 'delivery-tickets' => 'DeliveryTicketController'] as $prefix => $controller) {
            $router->group(['prefix' => $prefix], function () use ($router, $controller) {
                $router->post('/', $controller . '@create');
                $router->get('/', $controller . '@query');
                $router->delete('bulk-delete', $controller . '@bulkDelete');
                $router->get('{id}', $controller . '@find');
                $router->match(['put', 'patch'], '{id}', $controller . '@update');
                $router->delete('{id}', $controller . '@delete');
            });
        }

        // Load plans: drivers / plant staff confirm the load, attach the comprobante de carga, add late pedidos
        $router->group(['prefix' => 'load-plans'], function () use ($router) {
            $router->get('/', 'LoadPlanController@query');
            $router->get('{id}', 'LoadPlanController@find');
            $router->post('{id}/confirm-load', 'LoadPlanController@confirmLoad');
            $router->post('{id}/add-orders', 'LoadPlanController@addOrders');
            $router->post('{id}/files', 'LoadPlanController@uploadFile');
        });

        // Descargas: end-of-day unload to inventory from Navigator / inventory devices
        $router->group(['prefix' => 'unloads'], function () use ($router) {
            $router->get('prefill', 'UnloadController@prefill');
            $router->post('/', 'UnloadController@create');
            $router->get('/', 'UnloadController@query');
            $router->get('{id}', 'UnloadController@find');
            $router->post('{id}/confirm', 'UnloadController@confirm');
            $router->post('{id}/void', 'UnloadController@void');
            $router->post('{id}/files', 'UnloadController@uploadFile');
            $router->delete('{id}', 'UnloadController@delete');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Internal EnergyOps API Routes (console)
    |--------------------------------------------------------------------------
    */
    $router->prefix(config('energyops.api.routing.internal_prefix', 'int'))->namespace('Internal')->group(function ($router) {
        $router->prefix(config('energyops.api.routing.version_prefix', 'v1'))->namespace('v1')->group(function ($router) {
            $router->group(['middleware' => ['fleetbase.protected']], function ($router) {
                // Settings, dashboard summary & analytics
                $router->get('settings', 'SettingController@get');
                $router->post('settings', 'SettingController@save');
                $router->post('settings/install-order-configs', 'SettingController@installOrderConfigs');
                $router->get('summary', 'SettingController@summary');
                $router->get('analytics/variance', 'AnalyticsController@variance');
                $router->get('analytics/utilization', 'AnalyticsController@utilization');

                // Fleet page helpers
                $router->get('fleet/summary', 'FleetController@summary');
                $router->get('fleet/operations', 'FleetController@operations');

                // Packing / allocation
                $router->post('allocations/allocate', 'AllocationController@allocate');
                $router->post('allocate', 'AllocationController@allocate');

                // Catalog
                $router->fleetbaseRoutes('operations');
                $router->fleetbaseRoutes('products');
                $router->fleetbaseRoutes('compatibility-rules');

                // Fleet
                $router->fleetbaseRoutes('vehicle-profiles');
                $router->fleetbaseRoutes('compartments', function ($router, $controller) {
                    $router->get('for-owner/{ownerType}/{ownerId}', $controller('forOwner'));
                    $router->post('reorder', $controller('reorder'));
                    $router->post('{id}/flush', $controller('flush'));
                });
                $router->fleetbaseRoutes('customer-tanks');

                // Dispatch
                $router->fleetbaseRoutes('load-plans', function ($router, $controller) {
                    $router->post('{id}/confirm-load', $controller('confirmLoad'));
                    $router->post('{id}/add-orders', $controller('addOrders'));
                    $router->get('{id}/on-board-stock', $controller('onBoardStock'));
                    $router->post('{id}/close', $controller('close'));
                    $router->post('{id}/cancel', $controller('cancel'));
                    $router->post('{id}/reload', $controller('reload'));
                    $router->get('for-order/{orderId}', $controller('forOrder'));
                    $router->get('for-vehicle/{vehicleId}', $controller('forVehicle'));
                });
                $router->fleetbaseRoutes('load-lines');
                $router->fleetbaseRoutes('stop-draws');
                $router->fleetbaseRoutes('delivery-tickets', function ($router, $controller) {
                    $router->post('{id}/void', $controller('void'));
                    $router->post('{id}/exception', $controller('exception'));
                    $router->post('{id}/reject', $controller('reject'));
                    $router->get('for-order/{orderId}', $controller('forOrder'));
                });

                // Descargas (unload to inventory)
                $router->fleetbaseRoutes('unloads', function ($router, $controller) {
                    $router->get('prefill', $controller('prefill'));
                    $router->post('{id}/confirm', $controller('confirm'));
                    $router->post('{id}/void', $controller('void'));
                    $router->get('for-vehicle/{vehicleId}', $controller('forVehicle'));
                    $router->get('for-load-plan/{loadPlanId}', $controller('forLoadPlan'));
                });
                $router->fleetbaseRoutes('unload-lines');
            });
        });
    });
});
