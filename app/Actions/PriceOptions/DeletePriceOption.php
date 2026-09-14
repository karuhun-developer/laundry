<?php

namespace App\Actions\PriceOptions;

use App\Models\PriceOption;
use Illuminate\Validation\ValidationException;

final class DeletePriceOption
{
    public function handle(PriceOption $priceOption): void
    {
        if ($priceOption->orderItems()->exists()) {
            throw ValidationException::withMessages([
                'confirmation' => 'Paket yang sudah dipakai order tidak dapat dihapus. Nonaktifkan paket agar riwayat tetap aman.',
            ]);
        }

        $priceOption->delete();
    }
}
