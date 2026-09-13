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
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('free_delivery_radius_meters')->default(10000)->after('longitude');
            $table->unsignedInteger('delivery_fee_per_km_rupiah')->default(0)->after('free_delivery_radius_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'free_delivery_radius_meters',
                'delivery_fee_per_km_rupiah',
            ]);
        });
    }
};
