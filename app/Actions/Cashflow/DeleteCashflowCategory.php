<?php

namespace App\Actions\Cashflow;

use App\Models\CashflowCategory;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class DeleteCashflowCategory
{
    public function handle(CashflowCategory $category): void
    {
        if ($category->name === 'Pembayaran Pesanan' || $category->is_system) {
            throw new UnprocessableEntityHttpException('Kategori sistem dilindungi dan tidak dapat dihapus.');
        }

        if ($category->entries()->exists()) {
            throw new UnprocessableEntityHttpException('Kategori tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        $category->delete();
    }
}
