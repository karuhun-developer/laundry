<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(RoleName::Superadmin->value)
            ->syncPermissions(array_column(PermissionName::cases(), 'value'));

        Role::findOrCreate(RoleName::Owner->value)->syncPermissions([
            PermissionName::ManageBranches->value,
            PermissionName::ManageUsers->value,
            PermissionName::ManageCatalog->value,
            PermissionName::ManageOrders->value,
            PermissionName::UpdateOrderStatus->value,
            PermissionName::ViewAudit->value,
        ]);

        Role::findOrCreate(RoleName::BranchAdmin->value)->syncPermissions([
            PermissionName::ManageOrders->value,
            PermissionName::UpdateOrderStatus->value,
        ]);

        Role::findOrCreate(RoleName::Customer->value);
    }
}
