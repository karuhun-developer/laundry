<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedInteger('completion_duration_hours')->default(72);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('completion_duration_hours_snapshot')->nullable();
            $table->timestamp('estimated_ready_at')->nullable();
            $table->index(['branch_id', 'estimated_ready_at']);
        });
        DB::table('orders')->whereNotNull('priced_at')
            ->whereNotIn('status', ['ready_for_pickup', 'completed', 'cancelled'])
            ->select(['id', 'priced_at'])->orderBy('id')->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)->update([
                        'completion_duration_hours_snapshot' => 72,
                        'estimated_ready_at' => Carbon::parse($order->priced_at)->addHours(72),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'estimated_ready_at']);
            $table->dropColumn(['completion_duration_hours_snapshot', 'estimated_ready_at']);
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('completion_duration_hours');
        });
    }
};
