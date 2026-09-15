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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_address_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customer_addresses')
                ->nullOnDelete();
            $table->string('pickup_label_snapshot', 80)->nullable()->after('customer_longitude');
            $table->string('pickup_recipient_name_snapshot', 120)->nullable()->after('pickup_label_snapshot');
            $table->string('pickup_whatsapp_phone_snapshot', 20)->nullable()->after('pickup_recipient_name_snapshot');
            $table->text('pickup_address_snapshot')->nullable()->after('pickup_whatsapp_phone_snapshot');
            $table->text('pickup_notes_snapshot')->nullable()->after('pickup_address_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_address_id');
            $table->dropColumn([
                'pickup_label_snapshot',
                'pickup_recipient_name_snapshot',
                'pickup_whatsapp_phone_snapshot',
                'pickup_address_snapshot',
                'pickup_notes_snapshot',
            ]);
        });
    }
};
