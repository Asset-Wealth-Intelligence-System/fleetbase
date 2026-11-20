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
        Schema::create('energyops_load_plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('vehicle_uuid')->index();
            $table->uuid('operation_uuid')->nullable()->index();
            $table->uuid('manifest_uuid')->nullable()->index();
            $table->string('status', 32)->default('draft')->index();
            $table->decimal('total_planned_volume', 14, 3)->default(0);
            $table->decimal('total_loaded_volume', 14, 3)->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('loaded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
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
        Schema::dropIfExists('energyops_load_plans');
    }
};
