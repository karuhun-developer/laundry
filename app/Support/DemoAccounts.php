<?php

namespace App\Support;

use App\Enums\RoleName;

final class DemoAccounts
{
    public const PASSWORD = 'password';

    /**
     * @return list<array{name: string, label: string, email: string, role: string}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'Demo Customer', 'label' => 'Pelanggan', 'email' => 'customer@laundryhub.test', 'role' => RoleName::Customer->value],
            ['name' => 'Admin Kemang', 'label' => 'Admin Cabang', 'email' => 'branch@laundryhub.test', 'role' => RoleName::BranchAdmin->value],
            ['name' => 'Demo Owner', 'label' => 'Owner', 'email' => 'owner@laundryhub.test', 'role' => RoleName::Owner->value],
            ['name' => 'Super Admin', 'label' => 'Superadmin', 'email' => 'superadmin@laundryhub.test', 'role' => RoleName::Superadmin->value],
        ];
    }
}
