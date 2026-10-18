<?php

use App\Actions\Cashflow\GenerateDefaultCashflowCategories;
use App\Enums\CashflowType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CashflowCategory;
use App\Models\CashflowEntry;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows owner to download cashflow import template csv', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $response = $this->actingAs($owner)->get(route('cms.cashflow.template'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('template-import-cashflow.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('cabang,kategori,tipe,jumlah,tanggal,keterangan');
});

it('allows owner to export cashflow entries as csv', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create(['name' => 'Cabang Tebet']);
    app(GenerateDefaultCashflowCategories::class)->handle($organization);

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $category = CashflowCategory::query()
        ->where('organization_id', $organization->id)
        ->where('type', CashflowType::Credit)
        ->firstOrFail();

    CashflowEntry::factory()->for($organization)->create([
        'branch_id' => $branch->id,
        'cashflow_category_id' => $category->id,
        'type' => CashflowType::Credit,
        'amount_rupiah' => 750000,
        'description' => 'Beli parfum laundry 10 liter',
        'entry_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($owner)->get(route('cms.cashflow.export', [
        'year' => now()->year,
        'branch_id' => $branch->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $content = $response->streamedContent();
    expect($content)->toContain('Cabang Tebet')
        ->toContain('Beli parfum laundry 10 liter')
        ->toContain('750000');
});

it('imports cashflow entries from csv and auto-creates missing branches and categories', function (): void {
    $organization = Organization::factory()->create(['name' => 'Laundry Sejahtera']);
    $branch = Branch::factory()->for($organization)->create(['name' => 'Cabang Utama']);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $csvRows = [
        'cabang,kategori,tipe,jumlah,tanggal,keterangan',
        'Cabang Utama,Listrik,pengeluaran,350000,2026-03-01,Tagihan listrik bulan Maret',
        'Cabang Sudirman,Sponsor Banner,pemasukan,1200000,2026-03-05,Pemasukan sponsor banner',
    ];
    $csvContent = implode("\n", $csvRows);
    $file = UploadedFile::fake()->createWithContent('cashflow.csv', $csvContent);

    $response = $this->actingAs($owner)->post(route('cms.cashflow.import'), [
        'file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // 1. Verify new branch auto-created
    $newBranch = Branch::query()->where('organization_id', $organization->id)->where('name', 'Cabang Sudirman')->first();
    expect($newBranch)->not->toBeNull();

    // 2. Verify new category auto-created
    $newCategory = CashflowCategory::query()->where('organization_id', $organization->id)->where('name', 'Sponsor Banner')->first();
    expect($newCategory)->not->toBeNull();
    expect($newCategory->type)->toBe(CashflowType::Debit);

    // 3. Verify entries created
    $expense = CashflowEntry::query()
        ->where('organization_id', $organization->id)
        ->where('amount_rupiah', 350000)
        ->first();
    expect($expense)->not->toBeNull();
    expect($expense->type)->toBe(CashflowType::Credit);
    expect($expense->description)->toBe('Tagihan listrik bulan Maret');

    $income = CashflowEntry::query()
        ->where('organization_id', $organization->id)
        ->where('amount_rupiah', 1200000)
        ->first();
    expect($income)->not->toBeNull();
    expect($income->type)->toBe(CashflowType::Debit);
    expect($income->branch_id)->toBe($newBranch->id);
    expect($income->description)->toBe('Pemasukan sponsor banner');
});
