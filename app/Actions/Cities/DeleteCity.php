<?php

namespace App\Actions\Cities;

use App\Models\City;
use Illuminate\Validation\ValidationException;

final class DeleteCity
{
    public function handle(City $city): void
    {
        if ($city->branches()->exists()) {
            throw ValidationException::withMessages([
                'confirmation' => 'Kota masih digunakan oleh satu atau lebih cabang.',
            ]);
        }

        $city->delete();
    }
}
