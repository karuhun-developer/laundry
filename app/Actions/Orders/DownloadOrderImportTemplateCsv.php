<?php

namespace App\Actions\Orders;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadOrderImportTemplateCsv
{
    public function handle(): StreamedResponse
    {
        $filename = 'template-import-pesanan.csv';

        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Tidak dapat membuka output stream CSV.');
            }

            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'nama_pelanggan',
                'no_hp',
                'organisasi',
                'cabang',
                'layanan',
                'opsi_harga',
                'berat_kg',
                'total_biaya',
                'status_pembayaran',
                'metode_pembayaran',
                'status_pesanan',
                'tanggal',
                'catatan',
            ]);

            fputcsv($stream, [
                'Budi Santoso',
                '081234567890',
                'LaundryHub Demo',
                'Cabang Kemang',
                'Cuci Kering Lipat',
                'Kiloan reguler',
                '3.5',
                '42000',
                'lunas',
                'cash',
                'completed',
                now()->format('Y-m-d H:i:s'),
                'Pelanggan reguler',
            ]);

            fputcsv($stream, [
                'Siti Aminah',
                '089876543210',
                'LaundryHub Demo',
                'Cabang Kemang',
                'Cuci Kering Lipat',
                'Kiloan reguler',
                '2.0',
                '24000',
                'lunas',
                'qris',
                'completed',
                now()->format('Y-m-d H:i:s'),
                '',
            ]);

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
