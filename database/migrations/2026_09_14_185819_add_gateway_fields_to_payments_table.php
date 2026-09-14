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
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('gateway_driver', 32)->nullable();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->string('gateway_order_id', 100)->nullable();
            $table->string('gateway_method', 40)->nullable();
            $table->string('gateway_status', 40)->nullable();
            $table->text('gateway_payment_number')->nullable();
            $table->text('gateway_payment_url')->nullable();
            $table->timestamp('gateway_expires_at')->nullable();
            $table->json('gateway_payload')->nullable();

            $table->unique(['gateway_driver', 'gateway_order_id']);
            $table->index(['gateway_driver', 'gateway_payment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['gateway_driver', 'gateway_order_id']);
            $table->dropIndex(['gateway_driver', 'gateway_payment_id']);
            $table->dropColumn([
                'gateway_driver',
                'gateway_payment_id',
                'gateway_order_id',
                'gateway_method',
                'gateway_status',
                'gateway_payment_number',
                'gateway_payment_url',
                'gateway_expires_at',
                'gateway_payload',
            ]);
        });
    }
};
