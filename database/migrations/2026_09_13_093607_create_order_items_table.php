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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name_snapshot', 120);
            $table->string('service_code_snapshot', 50);
            $table->string('price_option_name_snapshot', 120);
            $table->string('price_type_snapshot', 32);
            $table->unsignedInteger('package_weight_grams_snapshot')->nullable();
            $table->unsignedBigInteger('base_price_rupiah_snapshot')->nullable();
            $table->unsignedBigInteger('price_per_kilogram_rupiah_snapshot')->nullable();
            $table->unsignedInteger('minimum_chargeable_grams_snapshot')->nullable();
            $table->unsignedInteger('requested_weight_grams');
            $table->unsignedInteger('chargeable_weight_grams');
            $table->unsignedBigInteger('calculated_base_price_rupiah');
            $table->unsignedInteger('excess_weight_grams');
            $table->unsignedBigInteger('excess_price_rupiah');
            $table->unsignedBigInteger('subtotal_rupiah');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
