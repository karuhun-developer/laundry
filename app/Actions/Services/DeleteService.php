<?php

namespace App\Actions\Services;

use App\Models\Service;
use Illuminate\Validation\ValidationException;

final class DeleteService
{
    public function handle(Service $service): void
    {
        if ($service->priceOptions()->exists() || $service->orderItems()->exists()) {
            throw ValidationException::withMessages([
                'confirmation' => 'Layanan masih memiliki paket atau riwayat order. Hapus paket yang belum dipakai atau nonaktifkan layanan.',
            ]);
        }

        $service->delete();
    }
}
