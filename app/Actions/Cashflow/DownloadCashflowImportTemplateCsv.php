<?php

namespace App\Actions\Cashflow;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadCashflowImportTemplateCsv
{
    public function handle(): StreamedResponse
    {
        $filename = 'template-import-cashflow.csv';

        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Tidak dapat membuka output stream CSV.');
            }

            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'cabang',
                'kategori',
                'tipe',
                'jumlah',
                'tanggal',
                'keterangan',
            ]);

            fputcsv($stream, [
                'Cabang Utama',
                'Listrik',
                'pengeluaran',
                '350000',
                now()->format('Y-m-d'),
                'Tagihan listrik bulanan',
            ]);

            fputcsv($stream, [
                'Cabang Utama',
                'Sabun & Deterjen',
                'pengeluaran',
                '150000',
                now()->format('Y-m-d'),
                'Beli deterjen 5 jerigen',
            ]);

            fputcsv($stream, [
                'Cabang Utama',
                'Pendapatan Lainnya',
                'pemasukan',
                '50000',
                now()->format('Y-m-d'),
                'Penjualan botol bekas',
            ]);

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
