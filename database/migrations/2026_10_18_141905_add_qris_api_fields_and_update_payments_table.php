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
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('qris_enabled')->default(true)->after('is_active');
            $table->text('qris_api_key')->nullable()->after('qris_enabled');
            $table->string('qris_base_url')->nullable()->after('qris_api_key');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('qris_static_payload');
            $table->text('qris_api_key')->nullable()->after('qris_enabled');
            $table->string('qris_base_url')->nullable()->after('qris_api_key');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('qris_token', 100)->nullable()->index()->after('qris_payload');
            $table->unsignedInteger('qris_unique_code')->nullable()->after('qris_token');
            $table->timestamp('qris_expires_at')->nullable()->after('qris_unique_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['qris_token']);
            $table->dropColumn(['qris_token', 'qris_unique_code', 'qris_expires_at']);
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['qris_api_key', 'qris_base_url']);
            $table->text('qris_static_payload')->nullable()->after('qris_enabled');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['qris_enabled', 'qris_api_key', 'qris_base_url']);
        });
    }
};
