<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
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

        $organization = Organization::query()
            ->where('slug', 'laundryhub-demo')
            ->firstOrFail();

        $this->assertSame(
            ['Bandung', 'Jakarta Selatan'],
            City::query()
                ->whereBelongsTo($organization)
                ->orderBy('name')
                ->pluck('name')
                ->all(),
        );
    }

    public function test_every_demo_account_can_log_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            'superadmin@laundryhub.test',
            'owner@laundryhub.test',
            'branch@laundryhub.test',
            'customer@laundryhub.test',
        ] as $email) {
            $response = $this->post(route('login.store'), [
                'email' => $email,
                'password' => 'password',
            ]);

            $response->assertRedirect(route('dashboard', absolute: false));
            $this->assertAuthenticatedAs(
                User::query()->where('email', $email)->firstOrFail(),
            );

            $this->post(route('logout'))->assertRedirect(route('home'));
        }
    }
}
