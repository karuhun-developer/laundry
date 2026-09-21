<?php

namespace App\Actions\Cashflow;

use App\Enums\CashflowGroup;
use App\Enums\CashflowType;
use App\Models\CashflowCategory;
use App\Models\Organization;

final class GenerateDefaultCashflowCategories
{
    /** @var array<int, array{name: string, type: CashflowType, group: CashflowGroup}> */
    private const SYSTEM_CATEGORIES = [
        // DEBIT (Pemasukan)
        ['name' => 'Pembayaran Pesanan', 'type' => CashflowType::Debit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Pendapatan Lainnya', 'type' => CashflowType::Debit, 'group' => CashflowGroup::NonRutin],

        // CREDIT (Pengeluaran)
        ['name' => 'Gaji (Salary)', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Sewa Tempat', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Listrik', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Air (PDAM)', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Internet', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Sabun & Deterjen', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Gas', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Transportasi', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Perawatan Mesin', 'type' => CashflowType::Credit, 'group' => CashflowGroup::Rutin],
        ['name' => 'Donasi', 'type' => CashflowType::Credit, 'group' => CashflowGroup::NonRutin],
        ['name' => 'Lain-lain', 'type' => CashflowType::Credit, 'group' => CashflowGroup::NonRutin],
    ];

    public function handle(Organization $organization): void
    {
        foreach (self::SYSTEM_CATEGORIES as $category) {
            CashflowCategory::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $category['name'],
                    'type' => $category['type'],
                ],
                [
                    'group' => $category['group'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
