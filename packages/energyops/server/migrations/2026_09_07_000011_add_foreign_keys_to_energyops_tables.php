<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Foreign keys for every EnergyOps table.
     *
     * Core / Fleet-Ops references: companies, users, vehicles, assets, places, orders, entities, manifests, manifest_stops.
     * Intra-extension references: operations, products, compartments, customer tanks, load plans, load lines, stop draws.
     */
    protected array $foreignKeys = [
        'energyops_operations' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['depot_place_uuid', 'places', 'SET NULL'],
        ],
        'energyops_products' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['operation_uuid', 'energyops_operations', 'SET NULL'],
        ],
        'energyops_compatibility_rules' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['from_product_uuid', 'energyops_products', 'CASCADE'],
            ['to_product_uuid', 'energyops_products', 'CASCADE'],
            ['from_operation_uuid', 'energyops_operations', 'CASCADE'],
            ['to_operation_uuid', 'energyops_operations', 'CASCADE'],
        ],
        'energyops_compartments' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['vehicle_uuid', 'vehicles', 'CASCADE'],
            ['asset_uuid', 'assets', 'CASCADE'],
            ['dedicated_product_uuid', 'energyops_products', 'SET NULL'],
            ['last_product_uuid', 'energyops_products', 'SET NULL'],
        ],
        'energyops_customer_tanks' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['place_uuid', 'places', 'CASCADE'],
            ['product_uuid', 'energyops_products', 'SET NULL'],
        ],
        'energyops_vehicle_profiles' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['vehicle_uuid', 'vehicles', 'CASCADE'],
            ['operation_uuid', 'energyops_operations', 'SET NULL'],
            ['coupled_asset_uuid', 'assets', 'SET NULL'],
        ],
        'energyops_load_plans' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['vehicle_uuid', 'vehicles', 'CASCADE'],
            ['operation_uuid', 'energyops_operations', 'SET NULL'],
            ['manifest_uuid', 'manifests', 'SET NULL'],
        ],
        'energyops_load_lines' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['load_plan_uuid', 'energyops_load_plans', 'CASCADE'],
            ['compartment_uuid', 'energyops_compartments', 'CASCADE'],
            ['product_uuid', 'energyops_products', 'CASCADE'],
            ['order_uuid', 'orders', 'SET NULL'],
            ['entity_uuid', 'entities', 'SET NULL'],
        ],
        'energyops_stop_draws' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['load_plan_uuid', 'energyops_load_plans', 'CASCADE'],
            ['load_line_uuid', 'energyops_load_lines', 'SET NULL'],
            ['order_uuid', 'orders', 'SET NULL'],
            ['manifest_stop_uuid', 'manifest_stops', 'SET NULL'],
            ['compartment_uuid', 'energyops_compartments', 'CASCADE'],
            ['product_uuid', 'energyops_products', 'SET NULL'],
        ],
        'energyops_delivery_tickets' => [
            ['company_uuid', 'companies', 'CASCADE'],
            ['created_by_uuid', 'users', 'SET NULL'],
            ['order_uuid', 'orders', 'SET NULL'],
            ['load_plan_uuid', 'energyops_load_plans', 'SET NULL'],
            ['stop_draw_uuid', 'energyops_stop_draws', 'SET NULL'],
            ['compartment_uuid', 'energyops_compartments', 'SET NULL'],
            ['product_uuid', 'energyops_products', 'SET NULL'],
            ['customer_tank_uuid', 'energyops_customer_tanks', 'SET NULL'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeys as $tableName => $keys) {
            Schema::table($tableName, function (Blueprint $table) use ($keys) {
                foreach ($keys as [$column, $references, $onDelete]) {
                    if (!Schema::hasTable($references)) {
                        continue;
                    }

                    $table->foreign([$column])->references(['uuid'])->on($references)->onUpdate('CASCADE')->onDelete($onDelete);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->foreignKeys as $tableName => $keys) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $keys) {
                foreach ($keys as [$column, $references]) {
                    if (!Schema::hasTable($references)) {
                        continue;
                    }

                    $table->dropForeign($tableName . '_' . $column . '_foreign');
                }
            });
        }
    }
};
