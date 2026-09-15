<?php

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PriceOption;
use App\Models\Service;

it('returns only active services and prices for the requested organization branch', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherBranch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create([
        'name' => 'Cuci Kering Lipat',
    ]);
    $activeOption = PriceOption::factory()->for($service)->for($branch)->create([
        'name' => 'Kiloan reguler',
    ]);

    PriceOption::factory()->for($service)->for($branch)->create([
        'name' => 'Tarif nonaktif',
        'is_active' => false,
    ]);
    PriceOption::factory()->for($service)->for($otherBranch)->create();
    PriceOption::factory()->for(
        Service::factory()->for($organization)->create(['is_active' => false]),
    )->for($branch)->create();
    PriceOption::factory()->create();

    $this->getJson(route('api.v1.organizations.branches.catalog', [
        'organization' => $organization,
        'branch' => $branch,
    ]))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $service->id)
        ->assertJsonCount(1, 'data.0.price_options')
        ->assertJsonPath('data.0.price_options.0.id', $activeOption->id)
        ->assertJsonPath('data.0.price_options.0.pricing.price_per_kilogram_rupiah', 12000);
});

it('makes an all-branches price available to existing and future branches', function (): void {
    $organization = Organization::factory()->create();
    $firstBranch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $globalOption = PriceOption::factory()->for($service)->forAllBranches()->create([
        'name' => 'Kiloan semua cabang',
    ]);
    $firstBranchOption = PriceOption::factory()->for($service)->for($firstBranch)->create([
        'name' => 'Kiloan khusus cabang',
    ]);
    $futureBranch = Branch::factory()->for($organization)->create();

    $firstCatalog = $this->getJson(route('api.v1.organizations.branches.catalog', [
        'organization' => $organization,
        'branch' => $firstBranch,
    ]))->assertOk();

    expect(collect($firstCatalog->json('data.0.price_options'))->pluck('id')->all())
        ->toContain($globalOption->id, $firstBranchOption->id);

    $futureCatalog = $this->getJson(route('api.v1.organizations.branches.catalog', [
        'organization' => $organization,
        'branch' => $futureBranch,
    ]))->assertOk()
        ->assertJsonCount(1, 'data.0.price_options')
        ->assertJsonPath('data.0.price_options.0.id', $globalOption->id);

    expect(collect($futureCatalog->json('data.0.price_options'))->pluck('id')->all())
        ->not->toContain($firstBranchOption->id);

    $this->postJson(route('api.v1.organizations.branches.catalog.quote', [
        'organization' => $organization,
        'branch' => $futureBranch,
        'priceOption' => $globalOption,
    ]), ['weight_grams' => 1000])->assertOk()
        ->assertJsonPath('data.total_rupiah', 12000);
});

it('quotes a per-kilogram option with its minimum chargeable weight', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create([
        'price_per_kilogram_rupiah' => 12000,
        'minimum_chargeable_grams' => 3000,
    ]);

    $this->postJson(route('api.v1.organizations.branches.catalog.quote', [
        'organization' => $organization,
        'branch' => $branch,
        'priceOption' => $priceOption,
    ]), ['weight_grams' => 1250])->assertOk()
        ->assertJsonPath('data.requested_weight_grams', 1250)
        ->assertJsonPath('data.chargeable_weight_grams', 3000)
        ->assertJsonPath('data.total_rupiah', 36000);
});

it('quotes fixed package excess and reports an unconfigured excess rate', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->fixedPackage()->for($service)->for($branch)->create();

    $route = route('api.v1.organizations.branches.catalog.quote', [
        'organization' => $organization,
        'branch' => $branch,
        'priceOption' => $priceOption,
    ]);

    $this->postJson($route, ['weight_grams' => 12500])->assertOk()
        ->assertJsonPath('data.excess_weight_grams', 500)
        ->assertJsonPath('data.excess_price_rupiah', 6000)
        ->assertJsonPath('data.total_rupiah', 126000);

    $priceOption->update(['price_per_kilogram_rupiah' => null]);

    $this->postJson($route, ['weight_grams' => 12500])->assertUnprocessable()
        ->assertJsonPath('code', 'PACKAGE_EXCESS_RATE_REQUIRED')
        ->assertJsonPath(
            'errors.weight_grams.0',
            'Berat melebihi batas paket dan tarif kelebihan belum diatur.',
        );
});

it('does not quote inactive or cross-tenant catalog records', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $otherOrganization = Organization::factory()->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();

    $this->getJson(route('api.v1.organizations.branches.catalog', [
        'organization' => $organization,
        'branch' => $otherBranch,
    ]))->assertNotFound();

    $this->postJson(route('api.v1.organizations.branches.catalog.quote', [
        'organization' => $otherOrganization,
        'branch' => $otherBranch,
        'priceOption' => $priceOption,
    ]), ['weight_grams' => 1000])->assertNotFound();

    $priceOption->update(['is_active' => false]);

    $this->postJson(route('api.v1.organizations.branches.catalog.quote', [
        'organization' => $organization,
        'branch' => $branch,
        'priceOption' => $priceOption,
    ]), ['weight_grams' => 1000])->assertNotFound();
});

it('validates quote weight as positive integer grams', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $service = Service::factory()->for($organization)->create();
    $priceOption = PriceOption::factory()->for($service)->for($branch)->create();

    $this->postJson(route('api.v1.organizations.branches.catalog.quote', [
        'organization' => $organization,
        'branch' => $branch,
        'priceOption' => $priceOption,
    ]), ['weight_grams' => 0])->assertUnprocessable()
        ->assertJsonValidationErrors('weight_grams');
});
