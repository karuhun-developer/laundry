<?php

namespace App\Actions\Users;

use App\Data\UserAccessData;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateUserWithAccess
{
    public function handle(UserAccessData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'organization_id' => $data->organizationId,
                'name' => $data->name,
                'email' => $data->email,
                'whatsapp_phone' => $data->whatsappPhone,
                'password' => $data->password,
            ]);

            $user->assignRole($data->role->value);
            $branchIds = $data->role === RoleName::BranchAdmin ? $data->branchIds : [];
            $user->branches()->sync($branchIds);

            activity('access')
                ->performedOn($user)
                ->withProperties([
                    'role' => $data->role->value,
                    'branch_ids' => $branchIds,
                ])
                ->log('user access created');

            return $user;
        });
    }
}
