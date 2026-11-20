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
        Schema::create('energyops_products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('_key')->nullable()->index();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 191)->nullable()->index();
            $table->uuid('company_uuid')->nullable()->index();
            $table->uuid('created_by_uuid')->nullable()->index();
            $table->uuid('operation_uuid')->nullable()->index();
            $table->string('code', 64)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('density', 10, 4)->nullable();
            $table->string('un_number', 16)->nullable();
            $table->string('hazmat_class', 16)->nullable();
            $table->string('default_unit', 16)->default('L');
            $table->string('skill', 64)->nullable();
            $table->string('color', 16)->nullable();
            $table->boolean('active')->default(true);
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
        Schema::dropIfExists('energyops_products');
    }
};
