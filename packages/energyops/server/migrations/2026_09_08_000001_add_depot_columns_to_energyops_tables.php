<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Multi-depot loading: a vehicle keeps its habitual loading station on its energy profile,
     * every load plan records where the product was actually loaded, and delivery tickets carry
     * the ERP (Dynamics) invoice number captured at the stop.
     */
    public function up(): void
    {
        Schema::table('energyops_vehicle_profiles', function (Blueprint $table) {
            $table->uuid('default_depot_place_uuid')->nullable()->index()->after('coupled_asset_uuid');
            if (Schema::hasTable('places')) {
                $table->foreign('default_depot_place_uuid')->references('uuid')->on('places')->onUpdate('CASCADE')->onDelete('SET NULL');
            }
        });

        Schema::table('energyops_load_plans', function (Blueprint $table) {
            $table->uuid('depot_place_uuid')->nullable()->index()->after('manifest_uuid');
            if (Schema::hasTable('places')) {
                $table->foreign('depot_place_uuid')->references('uuid')->on('places')->onUpdate('CASCADE')->onDelete('SET NULL');
            }
        });

        Schema::table('energyops_delivery_tickets', function (Blueprint $table) {
            $table->string('invoice_number', 64)->nullable()->index()->after('seal_out');
        });
    }

    public function down(): void
    {
        Schema::table('energyops_delivery_tickets', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
        });

        Schema::table('energyops_load_plans', function (Blueprint $table) {
            if (Schema::hasTable('places')) {
                $table->dropForeign('energyops_load_plans_depot_place_uuid_foreign');
            }
            $table->dropColumn('depot_place_uuid');
        });

        Schema::table('energyops_vehicle_profiles', function (Blueprint $table) {
            if (Schema::hasTable('places')) {
                $table->dropForeign('energyops_vehicle_profiles_default_depot_place_uuid_foreign');
            }
            $table->dropColumn('default_depot_place_uuid');
        });
    }
};
