<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('energyops_vehicle_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('vehicle_uuid')->unique();
            $table->uuid('operation_uuid')->nullable()->index();
            $table->uuid('coupled_asset_uuid')->nullable()->index();
            $table->boolean('is_tractor')->default(false);
            $table->decimal('max_payload_kg', 12, 3)->nullable();
            $table->decimal('pump_rate_lpm', 10, 2)->nullable();
            $table->boolean('has_meter')->default(true);
            $table->string('meter_serial', 64)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energyops_vehicle_profiles');
    }
};
