<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('price_options', function (Blueprint $table) {
            $table->unsignedBigInteger('min_price_per_kilogram_rupiah')
                ->nullable()
                ->after('price_per_kilogram_rupiah');
        });

        DB::table('price_options')
            ->where('type', 'per_kilogram')
            ->whereNull('min_price_per_kilogram_rupiah')
            ->update([
                'min_price_per_kilogram_rupiah' => DB::raw('price_per_kilogram_rupiah'),
            ]);

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('min_price_per_kilogram_rupiah_snapshot')
                ->nullable()
                ->after('price_per_kilogram_rupiah_snapshot');
        });

        DB::table('order_items')
            ->where('price_type_snapshot', 'per_kilogram')
            ->whereNull('min_price_per_kilogram_rupiah_snapshot')
            ->update([
                'min_price_per_kilogram_rupiah_snapshot' => DB::raw('price_per_kilogram_rupiah_snapshot'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('min_price_per_kilogram_rupiah_snapshot');
        });

        Schema::table('price_options', function (Blueprint $table) {
            $table->dropColumn('min_price_per_kilogram_rupiah');
        });
    }
};
