<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('name', 120);
            $table->unsignedInteger('package_weight_grams')->nullable();
            $table->unsignedBigInteger('base_price_rupiah')->nullable();
            $table->unsignedBigInteger('price_per_kilogram_rupiah')->nullable();
            $table->unsignedInteger('minimum_chargeable_grams')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'service_id', 'name']);
            $table->index(['branch_id', 'is_active']);
            $table->index(['service_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_options');
    }
};
