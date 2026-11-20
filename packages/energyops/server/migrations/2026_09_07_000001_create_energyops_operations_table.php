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
        Schema::create('energyops_operations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('depot_place_uuid')->nullable()->index();
            $table->string('code', 32)->index();
            $table->string('name');
            $table->string('family', 32)->default('glp')->index();
            $table->string('unit', 16)->default('L');
            $table->boolean('allow_split_delivery')->default(false);
            $table->boolean('block_over_ullage')->default(true);
            $table->unsignedInteger('default_service_seconds')->default(900);
            $table->string('status', 32)->default('active')->index();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_uuid', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energyops_operations');
    }
};
