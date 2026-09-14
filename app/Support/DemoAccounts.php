<?php

namespace App\Support;

use App\Enums\RoleName;

final class DemoAccounts
{
    public const PASSWORD = 'password';

    public const CUSTOMER_PIN = '123456';

    /**
     * @return list<array{name: string, label: string, email: string, role: string, whatsapp_phone?: string, pin?: string}>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'Pelanggan Demo',
                'label' => 'Pelanggan',
                'email' => 'customer@laundryhub.test',
                'role' => RoleName::Customer->value,
                'whatsapp_phone' => '6281234567890',
                'pin' => self::CUSTOMER_PIN,
            ],
            ['name' => 'Admin Kemang', 'label' => 'Admin Cabang', 'email' => 'branch@laundryhub.test', 'role' => RoleName::BranchAdmin->value],
            ['name' => 'Demo Owner', 'label' => 'Owner', 'email' => 'owner@laundryhub.test', 'role' => RoleName::Owner->value],
            ['name' => 'Super Admin', 'label' => 'Superadmin', 'email' => 'superadmin@laundryhub.test', 'role' => RoleName::Superadmin->value],
        ];
    }
}
