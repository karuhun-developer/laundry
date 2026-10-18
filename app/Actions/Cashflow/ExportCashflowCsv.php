<?php

namespace App\Actions\Cashflow;

use App\Models\CashflowEntry;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExportCashflowCsv
{
    /**
     * @param  Builder<CashflowEntry>  $query
     */
    public function handle(Builder $query): StreamedResponse
    {
        $filename = sprintf('laporan-cashflow-%s.csv', now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Tidak dapat membuka output stream CSV.');
            }

            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'ID',
                'Tanggal',
                'Cabang',
                'Kategori',
                'Tipe',
                'Kelompok',
                'Jumlah (Rp)',
                'Keterangan',
                'Dibuat Oleh',
            ]);

            $query->with(['branch:id,name', 'category:id,name,type,group', 'creator:id,name'])
                ->chunk(200, function ($entries) use ($stream): void {
                    foreach ($entries as $entry) {
                        fputcsv($stream, [
                            $entry->id,
                            $entry->entry_date->format('Y-m-d'),
                            $entry->branch->name,
                            $entry->category->name,
                            $entry->type->label(),
                            $entry->category->group->label(),
                            $entry->amount_rupiah,
                            $entry->description ?? '',
                            $entry->created_by_id !== null ? $entry->creator->name : 'Sistem',
                        ]);
                    }
                });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
