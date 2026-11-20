<?php

namespace Fleetbase\EnergyOps\Observers;

use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\Models\Company;

class CompanyObserver
{
    public function created(Company $company): void
    {
        EnergyOps::createOrderConfigs($company);
    }
}
