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

    public function test_reporting_roles_are_redirected_from_the_dashboard_alias_to_the_cms(): void
    {
        foreach ([RoleName::Owner, RoleName::Superadmin] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertRedirect(route('cms.dashboard'));
        }
    }

    public function test_owners_can_visit_the_reporting_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->assignRole(RoleName::Owner->value);
        $this->actingAs($user);

        $response = $this->get(route('cms.dashboard'));

        $response->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('filters.organization_id', $organization->id),
        );
    }

    public function test_direct_cms_access_is_role_aware_for_every_application_role(): void
    {
        $organization = Organization::factory()->create();

        foreach ([RoleName::Customer, RoleName::BranchAdmin, RoleName::Owner, RoleName::Superadmin] as $role) {
            $user = User::factory()->create(['organization_id' => $organization->id]);
            $user->assignRole($role->value);

            $request = $this->actingAs($user);

            if ($role === RoleName::Customer) {
                $request = $request->withSession(['customer_session_version' => $user->session_version]);
            }

            $response = $request->get(route('cms.dashboard'));

            if (in_array($role, [RoleName::Customer, RoleName::BranchAdmin], true)) {
                $response->assertRedirect(route('mobile.app'));

                continue;
            }

            $response->assertOk()->assertInertia(
                fn (Assert $page) => $page->component('Dashboard'),
            );
        }
    }

    public function test_mobile_roles_are_redirected_to_the_mobile_app(): void
    {
        foreach ([RoleName::Customer, RoleName::BranchAdmin] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $request = $this->actingAs($user);

            if ($role === RoleName::Customer) {
                $request = $request->withSession(['customer_session_version' => $user->session_version]);
            }

            $request->get(route('dashboard'))
                ->assertRedirect(route('mobile.app'));
        }
    }

    public function test_users_without_a_reporting_role_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }
}
