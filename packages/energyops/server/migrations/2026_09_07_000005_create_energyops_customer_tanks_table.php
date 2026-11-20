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
        Schema::create('energyops_customer_tanks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('place_uuid')->index();
            $table->uuid('product_uuid')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('serial_number', 64)->nullable()->index();
            $table->string('tank_type', 32)->default('aboveground');
            $table->decimal('capacity_volume', 12, 3)->default(0);
            $table->string('unit', 16)->default('L');
            $table->decimal('safe_fill_percent', 6, 2)->default(85);
            $table->decimal('last_known_volume', 12, 3)->default(0);
            $table->decimal('reorder_point', 12, 3)->nullable();
            $table->timestamp('last_delivery_at')->nullable();
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
        Schema::dropIfExists('energyops_customer_tanks');
    }
};
