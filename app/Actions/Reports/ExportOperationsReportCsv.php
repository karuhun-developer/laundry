<?php

namespace App\Actions\Reports;

use App\Data\Reports\OperationsReportData;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportOperationsReportCsv
{
    public function handle(OperationsReportData $report): StreamedResponse
    {
        $filename = sprintf(
            'laporan-operasional-%s-%s.csv',
            $report->filters->dateFrom->toDateString(),
            $report->filters->dateTo->toDateString(),
        );

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Tidak dapat membuka output laporan CSV.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, ['Laporan operasional LaundryHub']);
            $this->writeRow($stream, [
                'Periode',
                $report->filters->dateFrom->toDateString().' s.d. '.$report->filters->dateTo->toDateString(),
            ]);
            $this->writeRow($stream, []);
            $this->writeRow($stream, ['Ringkasan', 'Nilai']);
            $this->writeRow($stream, ['Pendapatan lunas (Rp)', $report->paidRevenueRupiah]);
            $this->writeRow($stream, ['Jumlah pesanan', $report->orderCount]);
            $this->writeRow($stream, [
                'Rata-rata turnaround (menit)',
                $report->averageTurnaroundMinutes ?? '',
            ]);
            $this->writeRow($stream, ['Pesanan dibatalkan', $report->cancelledOrderCount]);
            $this->writeRow($stream, ['Rasio pembatalan (%)', $report->cancellationRate]);
            $this->writeRow($stream, []);
            $this->writeRow($stream, [
                'Organisasi',
                'Kota',
                'Cabang',
                'Kode',
                'Pesanan',
                'Pendapatan lunas (Rp)',
                'Selesai',
                'Dibatalkan',
                'Rasio pembatalan (%)',
                'Rata-rata turnaround (menit)',
            ]);

            foreach ($report->branchComparisons as $branch) {
                $this->writeRow($stream, [
                    $branch['organization_name'],
                    $branch['city_name'],
                    $branch['branch_name'],
                    $branch['branch_code'],
                    $branch['order_count'],
                    $branch['paid_revenue_rupiah'],
                    $branch['completed_order_count'],
                    $branch['cancelled_order_count'],
                    $branch['cancellation_rate'],
                    $branch['average_turnaround_minutes'] ?? '',
                ]);
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  resource  $stream
     * @param  list<int|float|string>  $columns
     */
    private function writeRow($stream, array $columns): void
    {
        fputcsv($stream, $columns, ',', '"', '');
    }
}
