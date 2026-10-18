<?php

namespace App\Jobs;

use App\Actions\Orders\ImportOrdersCsv;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessOrderImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public string $importId,
        public string $filePath,
        public int $actorId,
        public int $totalRows,
    ) {}

    public function handle(ImportOrdersCsv $importOrders): void
    {
        /** @var User|null $actor */
        $actor = User::query()->find($this->actorId);

        if ($actor === null) {
            Storage::delete($this->filePath);
            Cache::put("order_import:{$this->importId}", [
                'id' => $this->importId,
                'status' => 'failed',
                'total_rows' => $this->totalRows,
                'processed_rows' => 0,
                'imported' => 0,
                'errors' => ['Pengguna tidak ditemukan.'],
                'error_count' => 1,
            ], now()->addDay());

            return;
        }

        $fullPath = Storage::path($this->filePath);

        if (! file_exists($fullPath)) {
            Cache::put("order_import:{$this->importId}", [
                'id' => $this->importId,
                'status' => 'failed',
                'total_rows' => $this->totalRows,
                'processed_rows' => 0,
                'imported' => 0,
                'errors' => ['Berkas CSV impor tidak ditemukan pada penyimpanan server.'],
                'error_count' => 1,
            ], now()->addDay());

            return;
        }

        Cache::put("order_import:{$this->importId}", [
            'id' => $this->importId,
            'status' => 'processing',
            'total_rows' => $this->totalRows,
            'processed_rows' => 0,
            'imported' => 0,
            'errors' => [],
            'error_count' => 0,
        ], now()->addDay());

        try {
            $result = $importOrders->handle(
                $fullPath,
                $actor,
                function (int $imported, array $errors, int $processed): void {
                    Cache::put("order_import:{$this->importId}", [
                        'id' => $this->importId,
                        'status' => 'processing',
                        'total_rows' => $this->totalRows,
                        'processed_rows' => min($this->totalRows, $processed),
                        'imported' => $imported,
                        'errors' => array_slice($errors, 0, 10),
                        'error_count' => count($errors),
                    ], now()->addDay());
                },
            );

            Cache::put("order_import:{$this->importId}", [
                'id' => $this->importId,
                'status' => 'completed',
                'total_rows' => $this->totalRows,
                'processed_rows' => $this->totalRows,
                'imported' => $result['imported'],
                'errors' => array_slice($result['errors'], 0, 10),
                'error_count' => count($result['errors']),
            ], now()->addDay());
        } catch (Throwable $e) {
            Cache::put("order_import:{$this->importId}", [
                'id' => $this->importId,
                'status' => 'failed',
                'total_rows' => $this->totalRows,
                'processed_rows' => 0,
                'imported' => 0,
                'errors' => [$e->getMessage()],
                'error_count' => 1,
            ], now()->addDay());

            throw $e;
        } finally {
            Storage::delete($this->filePath);
        }
    }
}
