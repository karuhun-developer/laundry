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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number', 40)->nullable()->unique();
            $table->string('status', 32);
            $table->string('customer_name_snapshot', 120);
            $table->decimal('customer_latitude', 10, 7)->nullable();
            $table->decimal('customer_longitude', 10, 7)->nullable();
            $table->unsignedInteger('delivery_distance_meters')->nullable();
            $table->unsignedInteger('delivery_free_radius_meters_snapshot')->nullable();
            $table->unsignedInteger('delivery_fee_per_km_rupiah_snapshot')->nullable();
            $table->unsignedBigInteger('items_subtotal_rupiah');
            $table->unsignedBigInteger('delivery_fee_rupiah')->default(0);
            $table->unsignedBigInteger('total_rupiah');
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
