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
        Schema::create('payment_gateway_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway_driver', 32);
            $table->string('delivery_id', 120);
            $table->string('event', 80);
            $table->json('payload');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['gateway_driver', 'delivery_id']);
            $table->index(['payment_id', 'processed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_deliveries');
    }
};
