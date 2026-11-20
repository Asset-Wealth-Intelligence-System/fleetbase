<?php

namespace Fleetbase\EnergyOps\Providers;

use Fleetbase\EnergyOps\Orchestration\Engines\CompartmentAllocationEngine;
use Fleetbase\EnergyOps\Services\AllocationService;
use Fleetbase\EnergyOps\Services\AnalyticsService;
use Fleetbase\EnergyOps\Services\CompartmentPacker;
use Fleetbase\EnergyOps\Services\LoadPlanService;
use Fleetbase\EnergyOps\Services\ReloadService;
use Fleetbase\EnergyOps\Services\TicketService;
use Fleetbase\EnergyOps\Services\UnloadService;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Orchestration\OrchestrationEngineRegistry;
use Fleetbase\FleetOps\Providers\FleetOpsServiceProvider;
use Fleetbase\Providers\CoreServiceProvider;

if (!class_exists(CoreServiceProvider::class)) {
    throw new \Exception('EnergyOps cannot be loaded without `fleetbase/core-api` installed!');
}

if (!class_exists(FleetOpsServiceProvider::class)) {
    throw new \Exception('EnergyOps cannot be loaded without `fleetbase/fleetops-api` installed!');
}

/**
 * EnergyOps service provider.
 *
 * Registers observers, console commands, model expansions, routes, migrations,
 * configuration and the compartment orchestration engine with Fleet-Ops.
 */
class EnergyOpsServiceProvider extends CoreServiceProvider
{
    /**
     * The observers registered with the service provider.
     *
     * @var array
     */
    public $observers = [
        \Fleetbase\Models\Company::class          => \Fleetbase\EnergyOps\Observers\CompanyObserver::class,
        \Fleetbase\FleetOps\Models\Order::class   => \Fleetbase\EnergyOps\Observers\OrderObserver::class,
        \Fleetbase\FleetOps\Models\Payload::class => \Fleetbase\EnergyOps\Observers\PayloadObserver::class,
        \Fleetbase\EnergyOps\Models\Product::class => \Fleetbase\EnergyOps\Observers\ProductObserver::class,
    ];

    /**
     * The console commands registered with the service provider.
     *
     * @var array
     */
    public $commands = [
        \Fleetbase\EnergyOps\Console\Commands\SeedUnitedCommand::class,
        \Fleetbase\EnergyOps\Console\Commands\InstallCommand::class,
        \Fleetbase\EnergyOps\Console\Commands\ImportCommand::class,
    ];

    public function register()
    {
        $this->app->register(CoreServiceProvider::class);
        $this->app->register(FleetOpsServiceProvider::class);

        $this->app->singleton(CompartmentPacker::class);
        $this->app->singleton(LoadPlanService::class);
        $this->app->singleton(TicketService::class);
        $this->app->singleton(UnloadService::class);
        $this->app->singleton(AllocationService::class);
        $this->app->singleton(ReloadService::class);
        $this->app->singleton(AnalyticsService::class);
        $this->app->singleton(CompartmentAllocationEngine::class);
    }

    public function boot()
    {
        $this->registerCommands();
        $this->registerObservers();
        $this->registerExpansionsFrom(__DIR__ . '/../Expansions');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../config/energyops.php', 'energyops');

        // Files are attached with `subject_type: energyops:load-plan|unload`, which core resolves to
        // `Fleetbase\Energyops\Models\…` (lower-case "ops"). PHP class names are case-insensitive but
        // the PSR-4 autoloader is not, so make sure both classes are loaded before any morph lookup.
        class_exists(\Fleetbase\EnergyOps\Models\LoadPlan::class);
        class_exists(\Fleetbase\EnergyOps\Models\Unload::class);

        // Register the compartment packer as a Fleet-Ops orchestration engine
        $this->app->resolving(OrchestrationEngineRegistry::class, function (OrchestrationEngineRegistry $registry) {
            if (!$registry->has(EnergyOps::ENGINE_ID)) {
                $registry->register($this->app->make(CompartmentAllocationEngine::class));
            }
        });
    }
}
