<?php

namespace Database\Seeders;

use App\Actions\Cashflow\GenerateDefaultCashflowCategories;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class CashflowCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $generateDefaultCashflowCategories = new GenerateDefaultCashflowCategories;

        Organization::query()->eachById(function (Organization $organization) use ($generateDefaultCashflowCategories): void {
            $generateDefaultCashflowCategories->handle($organization);
        });
    }
}
