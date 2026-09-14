<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class InvalidateCustomerSessions
{
    public function handle(User $customer, ?string $exceptSessionId = null): void
    {
        $customer->increment('session_version');
        $customer->refresh();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $customer->id)
            ->when(
                $exceptSessionId !== null,
                fn ($query) => $query->where('id', '!=', $exceptSessionId),
            )
            ->delete();
    }
}
