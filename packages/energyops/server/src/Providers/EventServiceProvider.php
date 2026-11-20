<?php

namespace Fleetbase\EnergyOps\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \Fleetbase\FleetOps\Events\OrderReady::class      => [\Fleetbase\EnergyOps\Listeners\HandleOrderReady::class],
        \Fleetbase\FleetOps\Events\OrderDispatched::class => [\Fleetbase\EnergyOps\Listeners\HandleOrderDispatched::class],
        \Fleetbase\FleetOps\Events\OrderCompleted::class  => [\Fleetbase\EnergyOps\Listeners\HandleOrderCompleted::class],
    ];
}
