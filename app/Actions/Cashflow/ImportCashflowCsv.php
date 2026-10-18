<?php

namespace App\Actions\Cashflow;

use App\Enums\CashflowGroup;
use App\Enums\CashflowType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ImportCashflowCsv
{
    public function __construct(
        private GenerateDefaultCashflowCategories $generateDefaultCashflowCategories,
    ) {}

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function handle(UploadedFile $file, User $actor): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return [
                'imported' => 0,
                'errors' => ['Gagal membuka berkas CSV yang diunggah.'],
            ];
        }

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false) {
            fclose($handle);

            return [
                'imported' => 0,
                'errors' => ['Berkas CSV kosong atau tidak memiliki baris header.'],
            ];
        }

        /** @var array<string, int> $headerMap */
        $headerMap = [];
        foreach ($headerRow as $index => $colName) {
            $normalized = Str::snake(trim(strtolower((string) $colName)));
            $headerMap[$normalized] = (int) $index;
        }

        $imported = 0;
        /** @var list<string> $errors */
        $errors = [];
        $rowNumber = 1;

        $isSuperadmin = $actor->hasRole(RoleName::Superadmin->value);

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($data, fn ($val): bool => trim((string) $val) !== ''))) {
                continue;
            }

            try {
                DB::transaction(function () use ($data, $headerMap, $actor, $isSuperadmin, $rowNumber): void {
                    $getValue = function (string ...$keys) use ($headerMap, $data): string {
                        foreach ($keys as $key) {
                            if (isset($headerMap[$key], $data[$headerMap[$key]])) {
                                $val = trim((string) $data[$headerMap[$key]]);
                                if ($val !== '') {
                                    return $val;
                                }
                            }
                        }

                        return '';
                    };

                    // 1. Organization resolution
                    $orgName = $getValue('organisasi');
                    if ($orgName !== '' && $isSuperadmin) {
                        $org = Organization::query()->where('name', $orgName)->first();
                        if ($org === null) {
                            $org = Organization::query()->create([
                                'name' => $orgName,
                                'slug' => Str::slug($orgName),
                                'is_active' => true,
                            ]);
                            $this->generateDefaultCashflowCategories->handle($org);
                        }
                    } else {
                        $org = $actor->organization ?? Organization::query()->first();
                    }

                    if ($org === null) {
                        throw new Exception("Baris {$rowNumber}: Organisasi tidak ditemukan atau belum dibuat.");
                    }

                    $this->generateDefaultCashflowCategories->handle($org);

                    // 2. Branch resolution (auto-create if missing)
                    $branchName = $getValue('cabang');
                    if ($branchName === '') {
                        $branchName = 'Cabang Utama';
                    }

                    $branch = Branch::query()
                        ->where('organization_id', $org->id)
                        ->where('name', $branchName)
                        ->first();

                    if ($branch === null) {
                        $cityId = $org->branches()->value('city_id')
                            ?? City::query()->firstOrCreate(['name' => 'Jakarta Selatan'], ['province' => 'DKI Jakarta'])->id;

                        $cleanCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $branchName) ?: 'CBG', 0, 3));
                        $branchCode = $cleanCode.random_int(10, 99);
                        while (Branch::query()->where('organization_id', $org->id)->where('code', $branchCode)->exists()) {
                            $branchCode = $cleanCode.random_int(10, 99);
                        }

                        $branch = Branch::query()->create([
                            'organization_id' => $org->id,
                            'city_id' => $cityId,
                            'name' => $branchName,
                            'code' => $branchCode,
                            'address' => $branchName,
                            'is_active' => true,
                        ]);
                    }

                    // 3. Category & Type resolution
                    $categoryName = $getValue('kategori');
                    if ($categoryName === '') {
                        $categoryName = 'Lain-lain';
                    }

                    $rawType = strtolower($getValue('tipe', 'jenis'));
                    $type = match ($rawType) {
                        'debit', 'pemasukan', 'masuk', 'income' => CashflowType::Debit,
                        'credit', 'kredit', 'pengeluaran', 'keluar', 'expense' => CashflowType::Credit,
                        default => null,
                    };

                    /** @var CashflowCategory|null $category */
                    $category = CashflowCategory::query()
                        ->where('organization_id', $org->id)
                        ->where('name', $categoryName)
                        ->first();

                    if ($category === null) {
                        $type = $type ?? CashflowType::Credit;
                        $category = CashflowCategory::query()->create([
                            'organization_id' => $org->id,
                            'name' => $categoryName,
                            'type' => $type,
                            'group' => CashflowGroup::NonRutin,
                            'is_system' => false,
                            'is_active' => true,
                        ]);
                    }

                    // 4. Amount resolution
                    $rawAmount = $getValue('jumlah', 'nominal', 'total');
                    $amount = (int) preg_replace('/[^0-9]/', '', $rawAmount);
                    if ($amount <= 0) {
                        throw new Exception("Baris {$rowNumber}: Jumlah nominal pengeluaran/pemasukan harus lebih dari 0.");
                    }

                    // 5. Date resolution
                    $rawDate = $getValue('tanggal', 'date');
                    try {
                        $entryDate = $rawDate !== '' ? Carbon::parse($rawDate) : now();
                    } catch (Exception) {
                        $entryDate = now();
                    }

                    // 6. Description
                    $description = $getValue('keterangan', 'catatan', 'deskripsi');
                    if ($description === '') {
                        $description = $category->name;
                    }

                    // 7. Store Cashflow Entry
                    CashflowEntry::query()->create([
                        'organization_id' => $org->id,
                        'branch_id' => $branch->id,
                        'cashflow_category_id' => $category->id,
                        'created_by_id' => $actor->id,
                        'type' => $category->type,
                        'amount_rupiah' => $amount,
                        'description' => $description,
                        'entry_date' => $entryDate->format('Y-m-d'),
                    ]);
                });

                $imported++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}
