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
        Schema::create('energyops_delivery_tickets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('order_uuid')->nullable()->index();
            $table->uuid('load_plan_uuid')->nullable()->index();
            $table->uuid('stop_draw_uuid')->nullable()->index();
            $table->uuid('compartment_uuid')->nullable()->index();
            $table->uuid('product_uuid')->nullable()->index();
            $table->uuid('customer_tank_uuid')->nullable()->index();
            $table->decimal('metered_qty', 12, 3)->default(0);
            $table->decimal('planned_qty', 12, 3)->nullable();
            $table->decimal('variance_qty', 12, 3)->nullable();
            $table->string('variance_reason')->nullable();
            $table->string('unit', 16)->default('L');
            $table->string('seal_in', 64)->nullable();
            $table->string('seal_out', 64)->nullable();
            $table->string('meter_start', 64)->nullable();
            $table->string('meter_end', 64)->nullable();
            $table->string('exception_type', 32)->nullable()->index();
            $table->string('status', 32)->default('issued')->index();
            $table->timestamp('issued_at')->nullable();
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
        Schema::dropIfExists('energyops_delivery_tickets');
    }
};
