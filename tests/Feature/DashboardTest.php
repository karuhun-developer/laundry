<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_owners_can_visit_the_reporting_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->assignRole(RoleName::Owner->value);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('filters.organization_id', $organization->id),
        );
    }

    public function test_mobile_roles_are_redirected_to_the_mobile_app(): void
    {
        foreach ([RoleName::Customer, RoleName::BranchAdmin] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertRedirect(route('mobile.app'));
        }
    }

    public function test_users_without_a_reporting_role_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }
}
