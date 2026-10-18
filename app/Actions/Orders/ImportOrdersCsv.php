<?php

namespace App\Actions\Orders;

use App\Actions\Cashflow\GenerateDefaultCashflowCategories;
use App\Actions\Cashflow\RecordPaymentCashflow;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PriceType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PriceOption;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ImportOrdersCsv
{
    public function __construct(
        private RecordPaymentCashflow $recordPaymentCashflow,
        private GenerateDefaultCashflowCategories $generateDefaultCashflowCategories,
    ) {}

    /**
     * @param  (callable(int, list<string>, int): void)|null  $onProgress
     * @return array{imported: int, errors: list<string>}
     */
    public function handle(UploadedFile|string $file, User $actor, ?callable $onProgress = null): array
    {
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $handle = fopen($realPath, 'rb');

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
                    $getValue = fn (string $key): string => isset($headerMap[$key], $data[$headerMap[$key]])
                        ? trim((string) $data[$headerMap[$key]])
                        : '';

                    $customerName = $getValue('nama_pelanggan');
                    if ($customerName === '') {
                        throw new Exception("Baris {$rowNumber}: Nama pelanggan wajib diisi.");
                    }

                    $rawPhone = $getValue('no_hp');
                    $trimmedPhone = trim($rawPhone);
                    $phone = null;

                    if ($trimmedPhone !== '' && $trimmedPhone !== '-' && $trimmedPhone !== '--') {
                        $digits = preg_replace('/[^0-9]/', '', $trimmedPhone);
                        if ($digits !== '') {
                            if (str_starts_with($digits, '0')) {
                                $digits = '62'.substr($digits, 1);
                            } elseif (str_starts_with($digits, '8')) {
                                $digits = '62'.$digits;
                            }
                            $phone = $digits;
                        }
                    }

                    // 1. Organization resolution
                    $orgName = $getValue('organisasi');
                    if ($isSuperadmin && $orgName !== '') {
                        $org = Organization::query()
                            ->where('name', $orgName)
                            ->orWhere('slug', Str::slug($orgName))
                            ->first();

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

                    // 2. Customer resolution & PIN setup (PIN: 676767)
                    if ($phone !== null) {
                        $customer = User::query()->where('whatsapp_phone', $phone)->first();
                    } else {
                        $customer = User::query()
                            ->where('name', $customerName)
                            ->whereNull('whatsapp_phone')
                            ->whereHas('customerOrganizations', fn ($q) => $q->where('organizations.id', $org->id))
                            ->first();
                    }

                    if ($customer === null) {
                        $customer = User::query()->create([
                            'name' => $customerName,
                            'whatsapp_phone' => $phone,
                            'email' => null,
                            'password' => null,
                            'session_version' => 1,
                        ]);
                        $customer->assignRole(RoleName::Customer->value);
                        if ($phone !== null) {
                            $customer->setCustomerPin('676767', null, false);
                        }
                        $customer->save();
                    }
                    $customer->customerOrganizations()->syncWithoutDetaching([$org->id]);

                    // 3. Branch resolution (auto-create if missing)
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

                    // 4. Service & PriceOption resolution (auto-create if missing)
                    $serviceName = $getValue('layanan');
                    if ($serviceName === '') {
                        $serviceName = 'Cuci Kering Lipat';
                    }

                    $service = Service::query()
                        ->where('organization_id', $org->id)
                        ->where('name', $serviceName)
                        ->first();

                    if ($service === null) {
                        $cleanSvcCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $serviceName) ?: 'SVC', 0, 3));
                        $svcCode = $cleanSvcCode.random_int(10, 99);
                        while (Service::query()->where('organization_id', $org->id)->where('code', $svcCode)->exists()) {
                            $svcCode = $cleanSvcCode.random_int(10, 99);
                        }

                        $service = Service::query()->create([
                            'organization_id' => $org->id,
                            'name' => $serviceName,
                            'code' => $svcCode,
                            'completion_duration_hours' => 72,
                            'is_active' => true,
                        ]);
                    }

                    $optionName = $getValue('opsi_harga');
                    if ($optionName === '') {
                        $optionName = 'Kiloan reguler';
                    }

                    $totalBiaya = (int) preg_replace('/[^0-9]/', '', $getValue('total_biaya') ?: '0');
                    $rawWeight = str_replace(',', '.', $getValue('berat_kg') ?: '1');
                    $weightKg = (float) $rawWeight > 0 ? (float) $rawWeight : 1.0;
                    $weightGrams = (int) round($weightKg * 1000);

                    $priceOption = PriceOption::query()
                        ->where('service_id', $service->id)
                        ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
                        ->where('name', $optionName)
                        ->first();

                    if ($priceOption === null) {
                        $pricePerKg = $weightKg > 0 && $totalBiaya > 0 ? (int) round($totalBiaya / $weightKg) : 10000;
                        $priceOption = PriceOption::query()->create([
                            'service_id' => $service->id,
                            'branch_id' => $branch->id,
                            'name' => $optionName,
                            'type' => PriceType::PerKilogram,
                            'price_per_kilogram_rupiah' => $pricePerKg,
                            'is_active' => true,
                        ]);
                    }

                    // 5. Order dates & status
                    $rawDate = $getValue('tanggal');
                    $date = $rawDate !== '' ? Carbon::parse($rawDate) : now();

                    $rawStatus = strtolower($getValue('status_pesanan') ?: 'completed');
                    $orderStatus = match ($rawStatus) {
                        'diterima', 'received' => OrderStatus::Received,
                        'ditimbang', 'weighed' => OrderStatus::Weighed,
                        'dicuci', 'washing' => OrderStatus::Washing,
                        'dikeringkan', 'drying' => OrderStatus::Drying,
                        'disetrika', 'ironing' => OrderStatus::Ironing,
                        'siap diambil', 'ready_for_pickup' => OrderStatus::ReadyForPickup,
                        'dibatalkan', 'cancelled' => OrderStatus::Cancelled,
                        default => OrderStatus::Completed,
                    };

                    // 6. Create Order & Items
                    $order = Order::query()->create([
                        'organization_id' => $org->id,
                        'branch_id' => $branch->id,
                        'customer_id' => $customer->id,
                        'created_by_id' => $actor->id,
                        'status' => $orderStatus,
                        'customer_name_snapshot' => $customer->name,
                        'customer_whatsapp_phone_snapshot' => $customer->whatsapp_phone,
                        'items_subtotal_rupiah' => $totalBiaya,
                        'delivery_fee_rupiah' => 0,
                        'total_rupiah' => $totalBiaya,
                        'priced_at' => $date,
                        'completion_duration_hours_snapshot' => $service->completion_duration_hours,
                        'estimated_ready_at' => $date,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    $order->forceFill([
                        'order_number' => sprintf('LDR-%s-%08d', $date->format('Ymd'), $order->id),
                    ])->save();

                    $order->items()->create([
                        'service_id' => $service->id,
                        'price_option_id' => $priceOption->id,
                        'service_name_snapshot' => $service->name,
                        'service_code_snapshot' => $service->code,
                        'price_option_name_snapshot' => $priceOption->name,
                        'price_type_snapshot' => $priceOption->type,
                        'package_weight_grams_snapshot' => $priceOption->package_weight_grams,
                        'base_price_rupiah_snapshot' => $priceOption->base_price_rupiah,
                        'price_per_kilogram_rupiah_snapshot' => $priceOption->price_per_kilogram_rupiah,
                        'min_price_per_kilogram_rupiah_snapshot' => $priceOption->effectiveMinPricePerKilogram(),
                        'minimum_chargeable_grams_snapshot' => $priceOption->minimum_chargeable_grams,
                        'requested_weight_grams' => $weightGrams,
                        'chargeable_weight_grams' => $weightGrams,
                        'calculated_base_price_rupiah' => $totalBiaya,
                        'excess_weight_grams' => 0,
                        'excess_price_rupiah' => 0,
                        'subtotal_rupiah' => $totalBiaya,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    // 7. Order Status Events
                    if ($orderStatus === OrderStatus::Completed) {
                        $stages = [
                            [OrderStatus::Received, 'Pesanan diterima'],
                            [OrderStatus::Weighed, 'Pesanan ditimbang'],
                            [OrderStatus::Washing, 'Mulai proses cuci'],
                            [OrderStatus::ReadyForPickup, 'Pesanan siap diambil'],
                            [OrderStatus::Completed, 'Pesanan selesai (Import data)'],
                        ];
                        foreach ($stages as [$st, $note]) {
                            $order->statusEvents()->create([
                                'actor_id' => $actor->id,
                                'actor_name_snapshot' => $actor->name,
                                'status' => $st,
                                'note' => $note,
                                'created_at' => $date,
                                'updated_at' => $date,
                            ]);
                        }
                    } else {
                        $order->statusEvents()->create([
                            'actor_id' => $actor->id,
                            'actor_name_snapshot' => $actor->name,
                            'status' => $orderStatus,
                            'note' => 'Diimpor dari CSV',
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);
                    }

                    // 8. Payment & Cashflow sync
                    $rawPaymentStatus = strtolower($getValue('status_pembayaran') ?: 'lunas');
                    $isPaid = in_array($rawPaymentStatus, ['lunas', 'paid', 'settled', 'success'], true);

                    $rawMethod = strtolower($getValue('metode_pembayaran') ?: 'cash');
                    $paymentMethod = match ($rawMethod) {
                        'qris' => PaymentMethod::Qris,
                        'transfer', 'gateway' => PaymentMethod::Gateway,
                        default => PaymentMethod::Cash,
                    };

                    if ($isPaid && $totalBiaya > 0) {
                        $payment = Payment::query()->create([
                            'organization_id' => $org->id,
                            'branch_id' => $branch->id,
                            'order_id' => $order->id,
                            'recorded_by_id' => $actor->id,
                            'method' => $paymentMethod,
                            'status' => PaymentStatus::Paid,
                            'amount_rupiah' => $totalBiaya,
                            'settled_at' => $date,
                            'note' => 'Pembayaran lunas (Import CSV)',
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);

                        $payment->forceFill([
                            'payment_number' => sprintf('PAY-%s-%08d', $date->format('Ymd'), $payment->id),
                        ])->save();

                        $payment->statusEvents()->create([
                            'actor_id' => $actor->id,
                            'actor_name_snapshot' => $actor->name,
                            'status' => PaymentStatus::Paid,
                            'note' => 'Pembayaran lunas',
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);

                        $this->generateDefaultCashflowCategories->handle($org);
                        $this->recordPaymentCashflow->handle($payment);
                    }
                });

                $imported++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }

            if ($onProgress !== null) {
                $onProgress($imported, $errors, $rowNumber - 1);
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}
