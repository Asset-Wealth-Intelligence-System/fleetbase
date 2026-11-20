<?php

namespace Fleetbase\EnergyOps\Console\Commands;

use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\Models\Company;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'energyops:install {--company=} {--enable} {--engine=compartment}';
    protected $description = 'Create EnergyOps order configs and optionally enable the extension for a company';

    public function handle(): int
    {
        $company = $this->resolveCompany();
        if (!$company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        EnergyOps::createOrderConfigs($company);
        EnergyOps::grantCompanyAccess($company);
        if ($this->option('enable')) {
            EnergyOps::enableForCompany($company, true);
            EnergyOps::setAllocationEngine($company, (string) $this->option('engine'));
        }

        $this->info('EnergyOps installed for ' . $company->name);

        return self::SUCCESS;
    }

    protected function resolveCompany(): ?Company
    {
        $id = $this->option('company');
        if ($id) {
            return Company::where('uuid', $id)->orWhere('public_id', $id)->orWhere('name', $id)->first();
        }

        return Company::first();
    }
}
