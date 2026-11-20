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
        Schema::create('energyops_compartments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->string('owner_type', 32)->default('vehicle');
            $table->uuid('owner_uuid')->index();
            $table->uuid('vehicle_uuid')->nullable()->index();
            $table->uuid('asset_uuid')->nullable()->index();
            $table->uuid('dedicated_product_uuid')->nullable()->index();
            $table->uuid('last_product_uuid')->nullable()->index();
            $table->string('code', 32);
            $table->string('name')->nullable();
            $table->unsignedInteger('sequence')->default(1);
            $table->decimal('capacity_volume', 12, 3)->default(0);
            $table->string('unit', 16)->default('L');
            $table->decimal('max_fill_percent', 6, 2)->default(85);
            $table->decimal('residual_volume', 12, 3)->default(0);
            $table->boolean('requires_flush')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['owner_type', 'owner_uuid']);
            $table->unique(['owner_type', 'owner_uuid', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energyops_compartments');
    }
};
