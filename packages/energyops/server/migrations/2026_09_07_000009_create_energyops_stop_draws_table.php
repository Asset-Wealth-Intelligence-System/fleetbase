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
        Schema::create('energyops_stop_draws', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('load_plan_uuid')->index();
            $table->uuid('load_line_uuid')->nullable()->index();
            $table->uuid('order_uuid')->nullable()->index();
            $table->uuid('manifest_stop_uuid')->nullable()->index();
            $table->uuid('compartment_uuid')->index();
            $table->uuid('product_uuid')->nullable()->index();
            $table->decimal('planned_qty', 12, 3)->default(0);
            $table->decimal('metered_qty', 12, 3)->nullable();
            $table->unsignedInteger('sequence')->default(1);
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
        Schema::dropIfExists('energyops_stop_draws');
    }
};
