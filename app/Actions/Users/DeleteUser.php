<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class DeleteUser
{
    public function handle(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'confirmation' => 'Akun yang sedang digunakan tidak dapat dihapus.',
            ]);
        }

        activity('access')
            ->causedBy($actor)
            ->performedOn($user)
            ->withProperties(['email' => $user->email])
            ->log('user deleted');

        $user->delete();
    }
}
