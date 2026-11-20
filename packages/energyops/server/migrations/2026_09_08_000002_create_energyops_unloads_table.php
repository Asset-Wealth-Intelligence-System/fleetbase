<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * DESCARGA: end-of-day return of the remaining product in the compartments to an inventory site.
     *
     * `unloaded_at` is the physical timestamp entered by the driver / inventory staff,
     * `created_at` is when the record was captured on the platform.
     */
    public function up(): void
    {
        Schema::create('energyops_unloads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('recorded_by_uuid')->nullable()->index();
            $table->uuid('vehicle_uuid')->nullable()->index();
            $table->uuid('driver_uuid')->nullable()->index();
            $table->uuid('load_plan_uuid')->nullable()->index();
            $table->uuid('manifest_uuid')->nullable()->index();
            $table->uuid('inventory_place_uuid')->nullable()->index();
            $table->uuid('inventory_tank_uuid')->nullable()->index();
            $table->string('unit', 16)->default('gal');
            $table->decimal('total_before_volume', 12, 3)->default(0);
            $table->decimal('total_unloaded_volume', 12, 3)->default(0);
            $table->decimal('total_remaining_volume', 12, 3)->default(0);
            $table->string('source', 16)->default('console')->index();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('unloaded_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('company_uuid')->references('uuid')->on('companies')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('created_by_uuid')->references('uuid')->on('users')->onUpdate('CASCADE')->onDelete('SET NULL');
            $table->foreign('recorded_by_uuid')->references('uuid')->on('users')->onUpdate('CASCADE')->onDelete('SET NULL');
            $table->foreign('load_plan_uuid')->references('uuid')->on('energyops_load_plans')->onUpdate('CASCADE')->onDelete('SET NULL');
            $table->foreign('inventory_tank_uuid')->references('uuid')->on('energyops_customer_tanks')->onUpdate('CASCADE')->onDelete('SET NULL');
        });

        Schema::table('energyops_unloads', function (Blueprint $table) {
            if (Schema::hasTable('vehicles')) {
                $table->foreign('vehicle_uuid')->references('uuid')->on('vehicles')->onUpdate('CASCADE')->onDelete('CASCADE');
            }
            if (Schema::hasTable('drivers')) {
                $table->foreign('driver_uuid')->references('uuid')->on('drivers')->onUpdate('CASCADE')->onDelete('SET NULL');
            }
            if (Schema::hasTable('manifests')) {
                $table->foreign('manifest_uuid')->references('uuid')->on('manifests')->onUpdate('CASCADE')->onDelete('SET NULL');
            }
            if (Schema::hasTable('places')) {
                $table->foreign('inventory_place_uuid')->references('uuid')->on('places')->onUpdate('CASCADE')->onDelete('SET NULL');
            }
        });

        Schema::create('energyops_unload_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('unload_uuid')->nullable()->index();
            $table->uuid('compartment_uuid')->nullable()->index();
            $table->uuid('product_uuid')->nullable()->index();
            $table->decimal('before_volume', 12, 3)->default(0);
            $table->decimal('unloaded_volume', 12, 3)->default(0);
            $table->decimal('remaining_volume', 12, 3)->default(0);
            $table->string('unit', 16)->default('gal');
            $table->integer('sequence')->default(0);
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('company_uuid')->references('uuid')->on('companies')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('unload_uuid')->references('uuid')->on('energyops_unloads')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('compartment_uuid')->references('uuid')->on('energyops_compartments')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('product_uuid')->references('uuid')->on('energyops_products')->onUpdate('CASCADE')->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energyops_unload_lines');
        Schema::dropIfExists('energyops_unloads');
    }
};
