<?php

namespace Database\Seeders;

use App\Enums\PriceType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use App\Models\Service;
use App\Models\User;
use App\Support\DemoAccounts;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['slug' => 'laundryhub-demo'],
            [
                'name' => 'LaundryHub Demo',
                'qris_enabled' => true,
                'qris_api_key' => 'qris_a00470db65b730b967bb35cf3ae6c93c7eab7e947a078608',
                'is_active' => true,
            ],
        );

        $jakartaCity = City::query()->updateOrCreate([
            'name' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
        ]);

        City::query()->updateOrCreate([
            'name' => 'Bandung',
            'province' => 'Jawa Barat',
        ]);

        $branch = Branch::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'KMG01'],
            [
                'city_id' => $jakartaCity->id,
                'name' => 'Cabang Kemang',
                'phone' => '081234567890',
                'address' => 'Jl. Kemang Raya No. 10, Jakarta Selatan',
                'latitude' => -6.2607,
                'longitude' => 106.8136,
                'free_delivery_radius_meters' => 10000,
                'delivery_fee_per_km_rupiah' => 3000,
                'qris_enabled' => true,
                'qris_api_key' => null,
                'qris_base_url' => 'https://qris.karuhundeveloper.com',
                'is_active' => true,
            ],
        );

        $service = Service::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'code' => 'CKL'],
            [
                'name' => 'Cuci Kering Lipat',
                'description' => 'Layanan cuci, kering, dan lipat untuk pakaian harian.',
                'is_active' => true,
            ],
        );

        $service->priceOptions()->updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Kiloan reguler'],
            [
                'type' => PriceType::PerKilogram,
                'price_per_kilogram_rupiah' => 12000,
                'minimum_chargeable_grams' => 1000,
                'package_weight_grams' => null,
                'base_price_rupiah' => null,
                'is_active' => true,
            ],
        );

        $service->priceOptions()->updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Paket 12 kg'],
            [
                'type' => PriceType::FixedPackage,
                'package_weight_grams' => 12000,
                'base_price_rupiah' => 12000,
                'price_per_kilogram_rupiah' => 5000,
                'minimum_chargeable_grams' => null,
                'is_active' => true,
            ],
        );

        foreach (DemoAccounts::all() as $account) {
            $organizationId = in_array($account['role'], [
                RoleName::Superadmin->value,
                RoleName::Customer->value,
            ], true) ? null : $organization->id;

            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'organization_id' => $organizationId,
                    'name' => $account['name'],
                    'whatsapp_phone' => $account['role'] === RoleName::Customer->value
                        ? $account['whatsapp_phone']
                        : null,
                    'email_verified_at' => now(),
                    'password' => DemoAccounts::PASSWORD,
                ],
            );

            if ($account['role'] === RoleName::Customer->value) {
                $user->setCustomerPin($account['pin']);
                $user->save();
            }

            $user->syncRoles([$account['role']]);

            if ($account['role'] === RoleName::BranchAdmin->value) {
                $user->branches()->sync([$branch->id]);
            }
        }
    }
}
