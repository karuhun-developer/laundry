<?php

namespace App\Actions\Users;

use App\Data\UserAccessData;
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
                'password' => $data->password,
            ]);

            $user->assignRole($data->role->value);
            $user->branches()->sync($data->branchIds);

            activity('access')
                ->performedOn($user)
                ->withProperties([
                    'role' => $data->role->value,
                    'branch_ids' => $data->branchIds,
                ])
                ->log('user access created');

            return $user;
        });
    }
}
