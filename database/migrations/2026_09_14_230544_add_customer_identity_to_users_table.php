<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('pin_hash')->nullable()->after('password');
            $table->timestamp('pin_expires_at')->nullable()->after('pin_hash');
            $table->boolean('must_change_pin')->default(false)->after('pin_expires_at');
            $table->unsignedBigInteger('session_version')->default(1)->after('must_change_pin');
        });

        $normalizedPhones = [];

        DB::table('users')
            ->whereNotNull('whatsapp_phone')
            ->orderBy('id')
            ->each(function (object $user) use (&$normalizedPhones): void {
                $digits = preg_replace('/\D+/', '', (string) $user->whatsapp_phone);

                if (is_string($digits) && str_starts_with($digits, '0')) {
                    $digits = '62'.substr($digits, 1);
                }

                if (! is_string($digits) || preg_match('/^628[1-9][0-9]{7,11}$/', $digits) !== 1) {
                    throw new RuntimeException(
                        "Nomor WhatsApp lama pada user {$user->id} tidak valid dan harus diperbaiki sebelum migrasi.",
                    );
                }

                if (isset($normalizedPhones[$digits])) {
                    throw new RuntimeException(
                        "Nomor WhatsApp {$digits} digunakan oleh lebih dari satu user setelah normalisasi.",
                    );
                }

                $normalizedPhones[$digits] = true;

                DB::table('users')->where('id', $user->id)->update([
                    'whatsapp_phone' => $digits,
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('whatsapp_phone');
        });

        Schema::create('customer_organization', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'organization_id']);
        });

        $customerIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'customer')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->pluck('model_has_roles.model_id');

        $now = now();
        $links = [];

        DB::table('users')
            ->whereIn('id', $customerIds)
            ->whereNotNull('organization_id')
            ->get(['id', 'organization_id'])
            ->each(function (object $user) use (&$links, $now): void {
                $links[$user->id.':'.$user->organization_id] = [
                    'customer_id' => $user->id,
                    'organization_id' => $user->organization_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });

        DB::table('orders')
            ->whereIn('customer_id', $customerIds)
            ->whereNotNull('customer_id')
            ->distinct()
            ->get(['customer_id', 'organization_id'])
            ->each(function (object $order) use (&$links, $now): void {
                $links[$order->customer_id.':'.$order->organization_id] = [
                    'customer_id' => $order->customer_id,
                    'organization_id' => $order->organization_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });

        if ($links !== []) {
            DB::table('customer_organization')->insert(array_values($links));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_organization');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_phone']);
            $table->dropColumn(['pin_hash', 'pin_expires_at', 'must_change_pin', 'session_version']);
        });

        DB::table('users')->whereNull('email')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'email' => "rollback-{$user->id}@invalid.test",
            ]);
        });

        DB::table('users')->whereNull('password')->update([
            'password' => Hash::make(Str::random(40)),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
