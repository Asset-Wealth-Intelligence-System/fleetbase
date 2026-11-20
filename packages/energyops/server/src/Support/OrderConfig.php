<?php

namespace Fleetbase\EnergyOps\Support;

use Fleetbase\Models\Company;

/**
 * Composer extra.fleetbase.order-config entry point.
 */
class OrderConfig
{
    public static function install(Company|string $company): array
    {
        return EnergyOps::createOrderConfigs($company);
    }
}
