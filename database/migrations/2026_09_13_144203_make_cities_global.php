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
        DB::transaction(function (): void {
            $duplicates = DB::table('cities')
                ->select(['name', 'province'])
                ->selectRaw('MIN(id) as canonical_id')
                ->groupBy(['name', 'province'])
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                $canonicalId = (int) $duplicate->canonical_id;
                $duplicateIds = DB::table('cities')
                    ->where('name', $duplicate->name)
                    ->where('province', $duplicate->province)
                    ->where('id', '!=', $canonicalId)
                    ->pluck('id');

                DB::table('branches')
                    ->whereIn('city_id', $duplicateIds)
                    ->update(['city_id' => $canonicalId]);

                DB::table('cities')->whereIn('id', $duplicateIds)->delete();
            }
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropUnique('cities_organization_id_name_province_unique');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->unique(['name', 'province']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'The global city migration cannot be reversed because historical organization ownership is no longer available.',
        );
    }
};
