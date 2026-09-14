<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\PriceOption;
use App\Models\User;
use App\Services\PriceCalculator;
use App\Support\DemoAccounts;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_seeded_with_explicit_credentials_and_access(): void
    {
        $this->seed(DatabaseSeeder::class);

        $expectedRoles = [
            'superadmin@laundryhub.test' => RoleName::Superadmin->value,
            'owner@laundryhub.test' => RoleName::Owner->value,
            'branch@laundryhub.test' => RoleName::BranchAdmin->value,
            'customer@laundryhub.test' => RoleName::Customer->value,
        ];

        foreach ($expectedRoles as $email => $role) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue(Hash::check('password', $user->password));
            $this->assertTrue($user->hasRole($role));
        }

        $this->assertSame(
            'Pelanggan Demo',
            User::query()->where('email', 'customer@laundryhub.test')->value('name'),
        );

        $branchAdmin = User::query()
            ->where('email', 'branch@laundryhub.test')
            ->firstOrFail();

        $this->assertTrue(
            $branchAdmin->branches()->where('code', 'KMG01')->exists(),
        );
    }

    public function test_demo_data_seeding_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, User::query()->count());
        $this->assertSame(1, Branch::query()->where('code', 'KMG01')->count());
    }

    public function test_demo_organization_has_multiple_selectable_cities(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ['Bandung', 'Jakarta Selatan'],
            City::query()
                ->orderBy('name')
                ->pluck('name')
                ->all(),
        );
    }

    public function test_demo_fixed_package_prices_decimal_excess_weight(): void
    {
        $this->seed(DatabaseSeeder::class);

        $priceOption = PriceOption::query()
            ->where('name', 'Paket 12 kg')
            ->firstOrFail();
        $quote = app(PriceCalculator::class)->quote($priceOption, 12500);

        $this->assertSame(12000, $priceOption->base_price_rupiah);
        $this->assertSame(5000, $priceOption->price_per_kilogram_rupiah);
        $this->assertSame(14500, $quote->totalRupiah);
    }

    public function test_every_demo_staff_account_can_log_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            'superadmin@laundryhub.test' => 'cms.dashboard',
            'owner@laundryhub.test' => 'cms.dashboard',
            'branch@laundryhub.test' => 'mobile.app',
        ] as $email => $routeName) {
            $response = $this->post(route('login.store'), [
                'email' => $email,
                'password' => DemoAccounts::PASSWORD,
            ]);

            $response->assertRedirect(route($routeName, absolute: false));
            $this->assertAuthenticatedAs(
                User::query()->where('email', $email)->firstOrFail(),
            );

            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }

    public function test_demo_customer_can_log_in_with_whatsapp_and_pin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::query()
            ->where('email', 'customer@laundryhub.test')
            ->firstOrFail();

        $response = $this->post(route('customer-login.store'), [
            'whatsapp_phone' => $customer->whatsapp_phone,
            'pin' => DemoAccounts::CUSTOMER_PIN,
        ]);

        $response->assertRedirect(route('mobile.app', absolute: false));
        $this->assertAuthenticatedAs($customer);

        $this->post(route('logout'))->assertRedirect(route('login'));
    }
}
