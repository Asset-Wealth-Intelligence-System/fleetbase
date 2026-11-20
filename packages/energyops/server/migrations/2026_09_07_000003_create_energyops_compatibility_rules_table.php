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
        Schema::create('energyops_compatibility_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('from_product_uuid')->nullable()->index();
            $table->uuid('to_product_uuid')->nullable()->index();
            $table->uuid('from_operation_uuid')->nullable()->index();
            $table->uuid('to_operation_uuid')->nullable()->index();
            $table->string('action', 32)->default('allowed')->index();
            $table->boolean('same_family_only')->default(true);
            $table->decimal('flush_volume', 12, 3)->nullable();
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('energyops_compatibility_rules');
    }
};
