<?php

namespace App\Actions\Branches;

use App\Models\Branch;
use Illuminate\Validation\ValidationException;

final class DeleteBranch
{
    public function handle(Branch $branch): void
    {
        if ($branch->orders()->exists()) {
            throw ValidationException::withMessages([
                'confirmation' => 'Cabang yang sudah memiliki order tidak dapat dihapus.',
            ]);
        }

        $branch->delete();
    }
}
