<?php

namespace App\Actions\Cashflow;

use App\Data\CashflowCategoryData;
use App\Models\CashflowCategory;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class UpdateCashflowCategory
{
    public function handle(CashflowCategory $category, CashflowCategoryData $data): CashflowCategory
    {
        if ($category->name === 'Pembayaran Pesanan') {
            throw new UnprocessableEntityHttpException('Kategori "Pembayaran Pesanan" dilindungi oleh sistem dan tidak dapat diubah.');
        }

        $category->update([
            'name' => $data->name,
            'group' => $data->group,
        ]);

        return $category;
    }
}
