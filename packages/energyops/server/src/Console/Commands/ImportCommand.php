<?php

namespace Fleetbase\EnergyOps\Console\Commands;

use Fleetbase\EnergyOps\Models\CompatibilityRule;
use Fleetbase\EnergyOps\Models\Compartment;
use Fleetbase\EnergyOps\Models\CustomerTank;
use Fleetbase\EnergyOps\Models\DeliveryTicket;
use Fleetbase\EnergyOps\Models\LoadLine;
use Fleetbase\EnergyOps\Models\LoadPlan;
use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\EnergyOps\Models\Product;
use Fleetbase\EnergyOps\Models\StopDraw;
use Fleetbase\EnergyOps\Models\VehicleProfile;
use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\Company;
use Fleetbase\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports the normalised JSON produced from the customer / product / truck workbooks.
 *
 * Idempotent: every entity is matched on a stable business key (operation/product `code`,
 * customer `internal_id`, place `_import_id`, tank `serial_number`, compartment `code` per owner,
 * driver name) so the command can be re-run after fixing the source data.
 *
 *   php artisan energyops:import /path/import.json --dry-run
 *   php artisan energyops:import /path/import.json --purge-dummy
 */
class ImportCommand extends Command
{
    protected $signature = 'energyops:import
        {file : Path to the normalised JSON file}
        {--company= : Company uuid (defaults to the uuid in the file)}
        {--purge-dummy : Hard-delete Energy Ops data, orders, places, contacts, drivers and assets of the company before importing}
        {--dry-run : Report what would happen without writing (runs inside a rolled-back transaction)}';

    protected $description = 'Import Energy Ops operations, products, customers, places, customer tanks, compartments and drivers from a normalised JSON file';

    /** @var array<string,int> */
    protected array $counts = [];

    /** @var array<int,string> */
    protected array $warnings = [];

    protected Company $company;

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            $this->error('File is not valid JSON.');

            return self::FAILURE;
        }

        $companyUuid = $this->option('company') ?: ($data['company_uuid'] ?? null);
        $company     = Company::where('uuid', $companyUuid)->first();
        if (!$company) {
            $this->error("Company not found: {$companyUuid}");

            return self::FAILURE;
        }
        $this->company = $company;
        session(['company' => $company->uuid]);

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Importing into {$company->name} ({$company->uuid})");

        DB::beginTransaction();
        try {
            if ($this->option('purge-dummy')) {
                $this->purge();
            }

            $products  = $this->importOperations($data['operations'] ?? []);
            $this->importCustomers($data['customers'] ?? [], $products);
            $vehicles  = $this->importVehicles($data['vehicles'] ?? [], $data['operations'] ?? []);
            $this->importDrivers($data['drivers'] ?? [], $vehicles);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Entity', 'Count'], collect($this->counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($this->warnings) {
            $this->newLine();
            $this->warn(count($this->warnings) . ' warning(s):');
            foreach ($this->warnings as $warning) {
                $this->line('  - ' . $warning);
            }
        }
        $this->info($dryRun ? 'Dry run complete — nothing was written.' : 'Import complete.');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ purge

    protected function purge(): void
    {
        $c = $this->company->uuid;

        // Energy Ops (children first, hard deletes)
        foreach ([DeliveryTicket::class, StopDraw::class, LoadLine::class, LoadPlan::class, CustomerTank::class, Compartment::class, VehicleProfile::class, CompatibilityRule::class, Product::class, Operation::class] as $model) {
            $n = $model::withTrashed()->where('company_uuid', $c)->count();
            $model::withTrashed()->where('company_uuid', $c)->forceDelete();
            $this->tick('purged ' . class_basename($model), $n);
        }

        // Fleet-Ops dummy orders and everything hanging off them
        // (orders first: FK cascades from payloads/tracking numbers would otherwise remove them silently)
        $payloadUuids = DB::table('payloads')->where('company_uuid', $c)->pluck('uuid');
        $this->tick('purged orders', DB::table('orders')->where('company_uuid', $c)->delete());
        $this->tick('purged tracking_statuses', DB::table('tracking_statuses')->where('company_uuid', $c)->delete());
        $this->tick('purged tracking_numbers', DB::table('tracking_numbers')->where('company_uuid', $c)->delete());
        $this->tick('purged entities', DB::table('entities')->where('company_uuid', $c)->delete());
        $this->tick('purged waypoints', DB::table('waypoints')->whereIn('payload_uuid', $payloadUuids)->delete());
        $this->tick('purged payloads', DB::table('payloads')->where('company_uuid', $c)->delete());
        $this->tick('purged manifests', DB::table('manifests')->where('company_uuid', $c)->delete());

        // Dummy places / customers / drivers / trailer asset (vehicles + fleets from telematics are kept)
        $this->tick('purged places', DB::table('places')->where('company_uuid', $c)->delete());
        $this->tick('purged contacts', DB::table('contacts')->where('company_uuid', $c)->delete());
        $driverUsers = DB::table('drivers')->where('company_uuid', $c)->pluck('user_uuid');
        $this->tick('purged drivers', DB::table('drivers')->where('company_uuid', $c)->delete());
        $this->tick('purged driver users', DB::table('users')->whereIn('uuid', $driverUsers)->where('type', 'driver')->delete());
        $this->tick('purged assets', DB::table('assets')->where('company_uuid', $c)->delete());

        // Clear Energy Ops keys left in vehicle meta by the old seed
        foreach (Vehicle::where('company_uuid', $c)->get() as $vehicle) {
            $meta = $vehicle->meta ?? [];
            $dirty = false;
            foreach (array_keys($meta) as $key) {
                if (Str::startsWith($key, 'energyops')) {
                    unset($meta[$key]);
                    $dirty = true;
                }
            }
            if ($dirty) {
                $vehicle->meta = $meta;
                $vehicle->saveQuietly();
            }
        }
    }

    // ------------------------------------------------------------- operations

    /** @return array<string,Product> keyed by product code */
    protected function importOperations(array $operations): array
    {
        $products = [];
        foreach ($operations as $op) {
            $operation = $this->upsert(Operation::class, 
                ['company_uuid' => $this->company->uuid, 'code' => $op['code']],
                [
                    'name'                    => $op['name'],
                    'family'                  => $op['family'] ?? 'other',
                    'unit'                    => $op['unit'] ?? 'gal',
                    'allow_split_delivery'    => (bool) ($op['allow_split_delivery'] ?? false),
                    'block_over_ullage'       => (bool) ($op['block_over_ullage'] ?? false),
                    'default_service_seconds' => $op['default_service_seconds'] ?? 900,
                    'status'                  => 'active',
                ]
            );
            $this->tick($operation->wasRecentlyCreated ? 'operations created' : 'operations updated');

            foreach ($op['products'] ?? [] as $p) {
                $product = $this->upsert(Product::class, 
                    ['company_uuid' => $this->company->uuid, 'code' => $p['code']],
                    [
                        'operation_uuid' => $operation->uuid,
                        'name'           => $p['name'],
                        'description'    => $p['description'] ?? null,
                        'density'        => $p['density'] ?? null,
                        'un_number'      => $p['un_number'] ?? null,
                        'hazmat_class'   => $p['hazmat_class'] ?? null,
                        'default_unit'   => $p['default_unit'] ?? $op['unit'] ?? 'gal',
                        'active'         => true,
                        'status'         => 'active',
                        'meta'           => $p['meta'] ?? [],
                    ]
                );
                $products[$p['code']] = $product;
                $this->tick($product->wasRecentlyCreated ? 'products created' : 'products updated');
            }
        }

        return $products;
    }

    // -------------------------------------------------------------- customers

    protected function importCustomers(array $customers, array $products): void
    {
        foreach ($customers as $cust) {
            $contact = $this->upsert(Contact::class, 
                ['company_uuid' => $this->company->uuid, 'type' => 'customer', 'internal_id' => $cust['internal_id']],
                [
                    'name'       => $cust['name'],
                    'title'      => $cust['contact_name'] ?: null,
                    'email'      => $cust['email'] ?: null,
                    'phone'      => $cust['phone'] ?: null,
                    'meta'       => ['contact_name' => $cust['contact_name'] ?: null, 'erp_customer_no' => $cust['internal_id']],
                ]
            );
            $this->tick($contact->wasRecentlyCreated ? 'customers created' : 'customers updated');

            $firstPlace = null;
            foreach ($cust['places'] ?? [] as $pl) {
                $importId = 'erp:' . $pl['ship_to_code'] . ':' . Str::slug($pl['name']);
                $hasCoords = $pl['latitude'] !== null && $pl['longitude'] !== null;
                $meta = array_filter([
                    'ship_to_code'          => $pl['ship_to_code'],
                    'reference'             => $pl['reference'],
                    'delivery_note'         => $pl['delivery_note'],
                    'receiving_hours'       => $pl['receiving_hours'],
                    'notify_customer'       => $pl['notify_customer'],
                    'required_vehicle_type' => $pl['required_vehicle_type'],
                    'priority'              => $pl['priority'],
                    'geocode_pending'       => $hasCoords ? null : true,
                    'needs_review'          => $pl['needs_review'] ?: null,
                ], fn ($v) => $v !== null);

                $place = $this->upsert(Place::class, 
                    ['company_uuid' => $this->company->uuid, '_import_id' => $importId],
                    [
                        'owner_uuid'   => $contact->uuid,
                        'owner_type'   => 'fleet-ops:contact',
                        'name'         => $pl['name'],
                        'type'         => 'place',
                        'street1'      => $pl['street1'],
                        'street2'      => $pl['street2'],
                        'neighborhood' => $pl['neighborhood'],
                        'city'         => $pl['city'],
                        'province'     => $pl['province'],
                        'postal_code'  => $pl['postal_code'],
                        'country'      => $pl['country'] ?: 'DO',
                        'phone'        => $pl['phone'],
                        'location'     => $hasCoords ? new Point($pl['latitude'], $pl['longitude']) : new Point(0, 0),
                        'meta'         => $meta,
                    ]
                );
                $firstPlace ??= $place;
                $this->tick($place->wasRecentlyCreated ? 'places created' : 'places updated');
                if (!$hasCoords) {
                    $this->warnings[] = "Place '{$pl['name']}' imported without coordinates: " . implode('; ', $pl['needs_review']);
                }

                foreach ($pl['tanks'] ?? [] as $tk) {
                    $product = $products[$tk['product_code']] ?? null;
                    if (!$product) {
                        $this->warnings[] = "Tank '{$tk['name']}' references unknown product code {$tk['product_code']}";
                    }
                    $match = ['company_uuid' => $this->company->uuid, 'place_uuid' => $place->uuid];
                    $match += $tk['serial_number'] ? ['serial_number' => $tk['serial_number']] : ['name' => $tk['name']];

                    $tank = $this->upsert(CustomerTank::class, $match, [
                        'product_uuid'      => $product?->uuid,
                        'name'              => $tk['name'],
                        'serial_number'     => $tk['serial_number'],
                        'tank_type'         => $tk['tank_type'] ?? 'aboveground',
                        'capacity_volume'   => $tk['capacity_volume'] ?? 0,
                        'unit'              => $tk['unit'] ?? 'gal',
                        'safe_fill_percent' => 80,
                        'status'            => 'active',
                        'meta'              => array_filter([
                            'capacity_pending' => $tk['capacity_pending'] ? true : null,
                            'erp_product_type' => $tk['erp_product_type'],
                            'needs_review'     => $tk['needs_review'] ?: null,
                        ], fn ($v) => $v !== null),
                    ]);
                    $this->tick($tank->wasRecentlyCreated ? 'customer tanks created' : 'customer tanks updated');
                }
            }

            if ($firstPlace && $contact->place_uuid !== $firstPlace->uuid) {
                $contact->place_uuid = $firstPlace->uuid;
                $contact->saveQuietly();
            }
        }
    }

    // --------------------------------------------------------------- vehicles

    /** @return array<string,Vehicle> keyed by call sign, only vehicles that exist and may be touched */
    protected function importVehicles(array $vehicles, array $operations): array
    {
        $resolved = [];
        foreach ($vehicles as $v) {
            $vehicle = Vehicle::where('company_uuid', $this->company->uuid)
                ->where(function ($q) use ($v) {
                    $q->where('call_sign', $v['call_sign']);
                    if (!empty($v['plate_number'])) {
                        $q->orWhere('plate_number', $v['plate_number']);
                    }
                })
                ->first();

            if (!$vehicle) {
                $this->tick('vehicles skipped (not in telematics)');
                continue;
            }
            if (!empty($v['identity_conflict'])) {
                $this->tick('vehicles skipped (identity conflict)');
                $this->warnings[] = "Vehicle {$v['call_sign']}: Excel says {$v['make']} {$v['model']} {$v['year']} plate {$v['plate_number']}, telematics has {$vehicle->make} {$vehicle->model} {$vehicle->year} plate {$vehicle->plate_number} — nothing loaded";
                continue;
            }
            $resolved[$v['call_sign']] = $vehicle;

            // Only fill what telematics leaves empty
            $changed = false;
            foreach (['vin' => $v['vin'], 'fuel_volume_unit' => $v['fuel_volume_unit'], 'measurement_system' => $v['measurement_system']] as $attr => $value) {
                if ($value && blank($vehicle->{$attr})) {
                    $vehicle->{$attr} = $value;
                    $changed = true;
                }
            }
            $meta = $vehicle->meta ?? [];
            foreach (array_filter(['fleetio_id' => $v['fleetio_id'], 'operator_name' => $v['operator_name'], 'erp_group' => $v['group']]) as $key => $value) {
                if (($meta[$key] ?? null) !== $value) {
                    $meta[$key] = $value;
                    $changed = true;
                }
            }
            if ($changed) {
                $vehicle->meta = $meta;
                $vehicle->saveQuietly();
                $this->tick('vehicles enriched');
            }

            if (empty($v['load_compartments']) || empty($v['compartments'])) {
                continue;
            }

            $operation = Operation::where('company_uuid', $this->company->uuid)->where('code', $v['operation_code'])->first();
            $profile = $this->upsert(VehicleProfile::class, 
                ['company_uuid' => $this->company->uuid, 'vehicle_uuid' => $vehicle->uuid],
                [
                    'operation_uuid' => $operation?->uuid,
                    'is_tractor'     => false,
                    'has_meter'      => false,
                    'status'         => 'active',
                    'meta'           => array_filter(['total_capacity_gal' => $v['total_gal'], 'needs_review' => $v['needs_review'] ?: null]),
                ]
            );
            $this->tick($profile->wasRecentlyCreated ? 'vehicle profiles created' : 'vehicle profiles updated');

            foreach ($v['compartments'] as $c) {
                $compartment = $this->upsert(Compartment::class, 
                    ['company_uuid' => $this->company->uuid, 'owner_type' => Compartment::OWNER_VEHICLE, 'owner_uuid' => $vehicle->uuid, 'code' => $c['code']],
                    [
                        'vehicle_uuid'     => $vehicle->uuid,
                        'name'             => 'Compartimiento ' . $c['sequence'],
                        'sequence'         => $c['sequence'],
                        'capacity_volume'  => $c['capacity_volume'],
                        'unit'             => $c['unit'] ?? 'gal',
                        'max_fill_percent' => 95,
                        'residual_volume'  => 0,
                        'requires_flush'   => false,
                        'status'           => 'active',
                    ]
                );
                $this->tick($compartment->wasRecentlyCreated ? 'compartments created' : 'compartments updated');
            }
            foreach ($v['needs_review'] ?? [] as $note) {
                $this->warnings[] = "Vehicle {$v['call_sign']}: {$note}";
            }
        }

        return $resolved;
    }

    // ---------------------------------------------------------------- drivers

    protected function importDrivers(array $drivers, array $vehicles): void
    {
        foreach ($drivers as $d) {
            $name = trim($d['name']);
            if ($name === '') {
                continue;
            }

            $driver = Driver::where('company_uuid', $this->company->uuid)
                ->whereHas('user', fn ($q) => $q->where('name', $name))
                ->first();

            if (!$driver) {
                $user = User::create([
                    'company_uuid' => $this->company->uuid,
                    'name'         => $name,
                    'password'     => Str::random(24),
                    'status'       => 'active',
                    'country'      => 'DO',
                    'timezone'     => 'America/Santo_Domingo',
                ]);
                $user->setType('driver');
                $user->assignCompany($this->company);
                $user->assignSingleRole('Driver');

                $driver = Driver::create([
                    'company_uuid' => $this->company->uuid,
                    'user_uuid'    => $user->uuid,
                    'slug'         => $user->slug,
                    'location'     => new Point(0, 0),
                    'country'      => 'DO',
                    'status'       => 'active',
                    'meta'         => ['source' => 'fleetio'],
                ]);
                $this->tick('drivers created');
            } else {
                $this->tick('drivers existing');
            }

            // Assign to the first of their trucks that exists in telematics (and has no identity conflict)
            $assigned = null;
            foreach ($d['vehicle_call_signs'] ?? [] as $callSign) {
                if (isset($vehicles[$callSign])) {
                    $assigned = $vehicles[$callSign];
                    break;
                }
            }
            if ($assigned && $driver->vehicle_uuid !== $assigned->uuid) {
                $driver->vehicle_uuid = $assigned->uuid;
                $driver->saveQuietly();
                $this->tick('drivers assigned to vehicle');
            } elseif (!$assigned) {
                $this->tick('drivers left unassigned');
            }
        }
    }

    /**
     * updateOrCreate that also restores a soft-deleted match.
     */
    protected function upsert(string $modelClass, array $match, array $attributes)
    {
        $model = $modelClass::withTrashed()->firstOrNew($match);
        $model->fill($attributes);
        if (method_exists($model, 'trashed') && $model->trashed()) {
            $model->deleted_at = null;
        }
        $model->save();

        return $model;
    }

    protected function tick(string $key, int $by = 1): void
    {
        if ($by === 0) {
            return;
        }
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $by;
    }
}
