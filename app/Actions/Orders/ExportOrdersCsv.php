<?php

namespace App\Actions\Orders;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExportOrdersCsv
{
    /**
     * @param  Builder<Order>  $query
     */
    public function handle(Builder $query): StreamedResponse
    {
        $filename = sprintf('laporan-pesanan-%s.csv', now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Tidak dapat membuka output stream CSV.');
            }

            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'No. Pesanan',
                'Tanggal',
                'Pelanggan',
                'No. WhatsApp',
                'Organisasi',
                'Cabang',
                'Status Pesanan',
                'Status Pembayaran',
                'Total (Rp)',
                'Sudah Dibayar (Rp)',
                'Sisa (Rp)',
            ]);

            $query->with(['organization:id,name', 'branch:id,name', 'payments'])
                ->chunk(200, function ($orders) use ($stream): void {
                    foreach ($orders as $order) {
                        $paidRupiah = (int) $order->payments
                            ->where('status', PaymentStatus::Paid)
                            ->sum('amount_rupiah');
                        $outstandingRupiah = max(0, $order->total_rupiah - $paidRupiah);
                        $paymentStatus = $outstandingRupiah === 0 && $order->total_rupiah > 0
                            ? 'Lunas'
                            : ($paidRupiah > 0 ? 'Sebagian' : 'Belum Lunas');

                        fputcsv($stream, [
                            $order->order_number ?? (string) $order->id,
                            $order->created_at->format('Y-m-d H:i:s'),
                            $order->customer_name_snapshot,
                            $order->customer_whatsapp_phone_snapshot ?? '-',
                            $order->organization->name,
                            $order->branch->name,
                            $order->status->label(),
                            $paymentStatus,
                            $order->total_rupiah,
                            $paidRupiah,
                            $outstandingRupiah,
                        ]);
                    }
                });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
