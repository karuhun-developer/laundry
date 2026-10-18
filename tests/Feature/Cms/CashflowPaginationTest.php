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
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('paginates cashflow entries on the table tab while preserving full annual statement', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    app(GenerateDefaultCashflowCategories::class)->handle($organization);

    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $category = CashflowCategory::query()
        ->where('organization_id', $organization->id)
        ->where('type', CashflowType::Credit)
        ->firstOrFail();

    // Create 30 entries of Rp 10.000 each = Rp 300.000 total credit
    for ($i = 1; $i <= 30; $i++) {
        CashflowEntry::factory()->for($organization)->create([
            'branch_id' => $branch->id,
            'cashflow_category_id' => $category->id,
            'type' => CashflowType::Credit,
            'amount_rupiah' => 10000,
            'entry_date' => now()->toDateString(),
        ]);
    }

    // Page 1: returns first 25 entries, full 30 in statement
    $this->actingAs($owner)
        ->get(route('cms.cashflow.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/Cashflow')
            ->has('entries', 25)
            ->where('pagination.current_page', 1)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 30)
            ->where('statement.total_credit', 300000)
        );

    // Page 2: returns remaining 5 entries with tab=tabel
    $this->actingAs($owner)
        ->get(route('cms.cashflow.index', ['page' => 2, 'tab' => 'tabel']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/Cashflow')
            ->has('entries', 5)
            ->where('pagination.current_page', 2)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 30)
            ->where('filters.tab', 'tabel')
            ->where('statement.total_credit', 300000)
        );
});
