<?php

namespace App\Actions\Users;

use App\Data\UpdateUserAccessData;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateUserWithAccess
{
    public function handle(User $user, UpdateUserAccessData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $attributes = [
                'name' => $data->name,
                'email' => $data->email,
                'whatsapp_phone' => $data->whatsappPhone,
            ];

            if ($data->password !== null) {
                $attributes['password'] = $data->password;
            }

            $user->update($attributes);
            $user->syncRoles([$data->role->value]);
            $branchIds = $data->role === RoleName::BranchAdmin ? $data->branchIds : [];
            $user->branches()->sync($branchIds);

            activity('access')
                ->performedOn($user)
                ->withProperties([
                    'role' => $data->role->value,
                    'branch_ids' => $branchIds,
                ])
                ->log('user access updated');

            return $user->refresh();
        });
    }
}
