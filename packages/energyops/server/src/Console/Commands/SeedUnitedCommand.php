<?php

namespace Fleetbase\EnergyOps\Console\Commands;

use Fleetbase\EnergyOps\Models\CompatibilityRule;
use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\EnergyOps\Models\Product;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Asset;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedUnitedCommand extends Command
{
    protected $signature = 'energyops:seed-united {--company=da3fdc68-04e9-4e93-adda-e3d339618f61}';
    protected $description = 'Seed United Petroleum GLP/WOP operations, products, compatibility, and compartment layouts';

    public function handle(): int
    {
        $company = Company::where('uuid', $this->option('company'))->first();
        if (!$company) {
            $this->error('United Petroleum company not found.');

            return self::FAILURE;
        }

        EnergyOps::createOrderConfigs($company);
        EnergyOps::enableForCompany($company, true);
        EnergyOps::setAllocationEngine($company, 'compartment');
        EnergyOps::grantCompanyAccess($company);

        $settings = EnergyOps::allocationSettings($company);
        $settings['auto_allocate_on_create'] = true;
        \Fleetbase\Models\Setting::configure('company.' . $company->uuid . '.fleet-ops.allocation-settings', $settings);

        $depot = Place::where('company_uuid', $company->uuid)->orderBy('created_at')->first();

        $glp = $this->operation($company, 'glp', 'GLP Rigido', 'kg', $depot?->uuid);
        $wop = $this->operation($company, 'wop', 'WOP Rigido', 'L', $depot?->uuid);

        $products = [
            'GLP_MIX'  => $this->product($company, $glp, 'GLP_MIX', 'GLP Mix', 0.54, 'kg', '1978'),
            'PROPANE'  => $this->product($company, $glp, 'PROPANE', 'Propane', 0.51, 'kg', '1978'),
            'BUTANE'   => $this->product($company, $glp, 'BUTANE', 'Butane', 0.58, 'kg', '1011'),
            'DIESEL'   => $this->product($company, $wop, 'DIESEL', 'Diesel', 0.84, 'L', '1202'),
            'REGULAR'  => $this->product($company, $wop, 'REGULAR', 'Regular gasoline', 0.74, 'L', '1203'),
            'PREMIUM'  => $this->product($company, $wop, 'PREMIUM', 'Premium gasoline', 0.74, 'L', '1203'),
        ];

        $this->rules($company, $glp, $wop, $products);
        $this->vehicles($company, $glp, $wop, $products);

        $this->info('United EnergyOps seed complete.');
        $this->line('Operations: ' . Operation::where('company_uuid', $company->uuid)->count());
        $this->line('Products: ' . Product::where('company_uuid', $company->uuid)->count());
        $this->line('Compartments: ' . Compartment::where('company_uuid', $company->uuid)->count());
        $this->line('Profiles: ' . VehicleProfile::where('company_uuid', $company->uuid)->count());

        return self::SUCCESS;
    }

    protected function operation(Company $company, string $code, string $name, string $unit, ?string $depotUuid): Operation
    {
        return Operation::updateOrCreate(
            ['company_uuid' => $company->uuid, 'code' => $code],
            [
                'name'                    => $name,
                'unit'                    => $unit,
                'depot_place_uuid'        => $depotUuid,
                'allow_split_delivery'    => false,
                'block_over_ullage'       => false,
                'default_service_seconds' => 900,
                'status'                  => 'active',
            ]
        );
    }

    protected function product(Company $company, Operation $operation, string $code, string $name, float $density, string $unit, string $un): Product
    {
        return Product::updateOrCreate(
            ['company_uuid' => $company->uuid, 'code' => $code],
            [
                'operation_uuid' => $operation->uuid,
                'name'           => $name,
                'density'        => $density,
                'default_unit'   => $unit,
                'un_number'      => $un,
                'hazmat_class'   => '3',
                'skill'          => Str::slug($code, '_'),
                'active'         => true,
                'status'         => 'active',
            ]
        );
    }

    protected function rules(Company $company, Operation $glp, Operation $wop, array $products): void
    {
        CompatibilityRule::updateOrCreate(
            [
                'company_uuid'         => $company->uuid,
                'from_operation_uuid'  => $glp->uuid,
                'to_operation_uuid'    => $wop->uuid,
            ],
            ['action' => CompatibilityRule::FORBIDDEN, 'same_family_only' => true, 'status' => 'active']
        );
        CompatibilityRule::updateOrCreate(
            [
                'company_uuid'         => $company->uuid,
                'from_operation_uuid'  => $wop->uuid,
                'to_operation_uuid'    => $glp->uuid,
            ],
            ['action' => CompatibilityRule::FORBIDDEN, 'same_family_only' => true, 'status' => 'active']
        );

        foreach ([['PROPANE', 'BUTANE'], ['BUTANE', 'PROPANE'], ['GLP_MIX', 'PROPANE'], ['GLP_MIX', 'BUTANE']] as [$from, $to]) {
            CompatibilityRule::updateOrCreate(
                [
                    'company_uuid'      => $company->uuid,
                    'from_product_uuid' => $products[$from]->uuid,
                    'to_product_uuid'   => $products[$to]->uuid,
                ],
                ['action' => CompatibilityRule::FLUSH_REQUIRED, 'same_family_only' => true, 'status' => 'active']
            );
        }

        foreach ([['DIESEL', 'REGULAR'], ['DIESEL', 'PREMIUM'], ['REGULAR', 'DIESEL'], ['PREMIUM', 'DIESEL']] as [$from, $to]) {
            CompatibilityRule::updateOrCreate(
                [
                    'company_uuid'      => $company->uuid,
                    'from_product_uuid' => $products[$from]->uuid,
                    'to_product_uuid'   => $products[$to]->uuid,
                ],
                ['action' => CompatibilityRule::FLUSH_REQUIRED, 'same_family_only' => true, 'status' => 'active']
            );
        }

        CompatibilityRule::updateOrCreate(
            [
                'company_uuid'      => $company->uuid,
                'from_product_uuid' => $products['REGULAR']->uuid,
                'to_product_uuid'   => $products['PREMIUM']->uuid,
            ],
            ['action' => CompatibilityRule::ALLOWED, 'same_family_only' => true, 'status' => 'active']
        );
        CompatibilityRule::updateOrCreate(
            [
                'company_uuid'      => $company->uuid,
                'from_product_uuid' => $products['PREMIUM']->uuid,
                'to_product_uuid'   => $products['REGULAR']->uuid,
            ],
            ['action' => CompatibilityRule::FLUSH_REQUIRED, 'same_family_only' => true, 'status' => 'active']
        );
    }

    protected function vehicles(Company $company, Operation $glp, Operation $wop, array $products): void
    {
        $layouts = [
            '13435' => ['op' => 'glp', 'cells' => [['C1', 8000, 'GLP_MIX']]],
            '13573' => ['op' => 'glp', 'cells' => [['C1', 5000, 'GLP_MIX'], ['C2', 5000, null]]],
            '13922' => ['op' => 'glp', 'cells' => [['C1', 8000, 'GLP_MIX']]],
            '15155' => ['op' => 'glp', 'cells' => [['C1', 4500, 'GLP_MIX'], ['C2', 4500, null]]],
            '15544' => ['op' => 'glp', 'cells' => [['C1', 8000, 'GLP_MIX']]],
            '15883' => ['op' => 'glp', 'cells' => [['C1', 5000, 'GLP_MIX'], ['C2', 4000, null]]],
            '15956' => ['op' => 'glp', 'cells' => [['C1', 8000, 'GLP_MIX']]],
            '16051' => ['op' => 'glp', 'cells' => [['C1', 4000, 'GLP_MIX'], ['C2', 4000, null]]],
            '13283' => ['op' => 'wop', 'tractor' => true, 'cells' => [['C1', 8000, 'DIESEL'], ['C2', 6000, 'REGULAR'], ['C3', 6000, 'PREMIUM']]],
            '15221' => ['op' => 'wop', 'cells' => [['C1', 8000, 'DIESEL'], ['C2', 5000, null]]],
            '15534' => ['op' => 'wop', 'cells' => [['C1', 6000, 'DIESEL'], ['C2', 4000, 'REGULAR'], ['C3', 4000, 'PREMIUM']]],
            '15619' => ['op' => 'wop', 'cells' => [['C1', 8000, 'DIESEL'], ['C2', 4000, 'REGULAR'], ['C3', 4000, 'PREMIUM']]],
            '15804' => ['op' => 'wop', 'cells' => [['C1', 7000, 'DIESEL'], ['C2', 5000, null]]],
            '15912' => ['op' => 'wop', 'cells' => [['C1', 7000, 'DIESEL'], ['C2', 5000, 'REGULAR']]],
            '16328' => ['op' => 'wop', 'cells' => [['C1', 6000, 'DIESEL'], ['C2', 4000, 'REGULAR'], ['C3', 4000, 'PREMIUM']]],
        ];

        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('company_uuid', $company->uuid)
            ->get()
            ->keyBy(fn (Vehicle $v) => (string) $v->internal_id);

        foreach ($layouts as $carId => $layout) {
            $vehicle = $vehicles->get((string) $carId);
            if (!$vehicle) {
                $this->warn("Vehicle {$carId} not found, skipped.");
                continue;
            }

            $operation = $layout['op'] === 'glp' ? $glp : $wop;
            $isTractor = !empty($layout['tractor']);
            $owner     = $vehicle;
            $ownerType = Compartment::OWNER_VEHICLE;
            $assetUuid = null;

            if ($isTractor) {
                $asset = $this->firstOrCreateTankerAsset($company);
                $assetUuid = $asset->uuid;
                $owner     = $asset;
                $ownerType = Compartment::OWNER_ASSET;
            }

            VehicleProfile::updateOrCreate(
                ['vehicle_uuid' => $vehicle->uuid],
                [
                    'company_uuid'       => $company->uuid,
                    'operation_uuid'     => $operation->uuid,
                    'is_tractor'         => $isTractor,
                    'coupled_asset_uuid' => $assetUuid,
                    'max_payload_kg'     => $layout['op'] === 'glp' ? 9000 : 18000,
                    'status'             => 'active',
                ]
            );

            $skills = array_values(array_unique(array_merge($vehicle->skills ?? [], [$layout['op']])));
            $vehicle->skills = $skills;
            $totalLiters     = array_sum(array_column($layout['cells'], 1));
            $vehicle->payload_capacity_volume = $totalLiters / 1000;
            $vehicle->save();

            foreach ($layout['cells'] as $index => $cell) {
                [$code, $capacity, $dedicated] = $cell;
                Compartment::updateOrCreate(
                    [
                        'company_uuid' => $company->uuid,
                        'owner_uuid'   => $owner->uuid,
                        'code'         => $code,
                    ],
                    [
                        'owner_type'             => $ownerType,
                        'vehicle_uuid'           => $ownerType === Compartment::OWNER_VEHICLE ? $owner->uuid : null,
                        'asset_uuid'             => $ownerType === Compartment::OWNER_ASSET ? $owner->uuid : null,
                        'name'                   => 'Compartment ' . ($index + 1),
                        'sequence'               => $index + 1,
                        'capacity_volume'        => $capacity,
                        'unit'                   => 'L',
                        'max_fill_percent'       => $layout['op'] === 'glp' ? 85 : 95,
                        'dedicated_product_uuid' => $dedicated ? $products[$dedicated]->uuid : null,
                        'residual_volume'        => 0,
                        'requires_flush'         => false,
                        'status'                 => 'active',
                    ]
                );
            }
        }
    }

    protected function firstOrCreateTankerAsset(Company $company): Asset
    {
        $existing = Asset::withoutGlobalScopes()
            ->where('company_uuid', $company->uuid)
            ->where('code', 'F4-TANKER')
            ->first();
        if ($existing) {
            return $existing;
        }

        $uuid = (string) Str::uuid();
        DB::table('assets')->insert([
            'uuid'         => $uuid,
            'company_uuid' => $company->uuid,
            'name'         => 'F4 Tanker',
            'code'         => 'F4-TANKER',
            'type'         => 'tanker',
            'status'       => 'active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return Asset::withoutGlobalScopes()->where('uuid', $uuid)->firstOrFail();
    }
}
