<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('lets owners manage templates only inside their organization', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $organizationTemplate = MessageTemplate::factory()->for($organization)->create([
        'order_status' => OrderStatus::Washing,
        'scope_key' => 'organization',
        'branch_id' => null,
    ]);
    $otherTemplate = MessageTemplate::factory()->for($otherOrganization)->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);

    $this->actingAs($owner)
        ->get(route('cms.message-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cms/MessageTemplates')
            ->has('messageTemplates', 1)
            ->where('messageTemplates.0.id', $organizationTemplate->id)
            ->where('messageTemplates.0.branch_id', $branch->id)
            ->where('messageTemplates.0.scope_key', 'branch:'.$branch->id)
            ->where('messageTemplates.0.is_inherited', true)
            ->has('branches', 1)
            ->where('branches.0.id', $branch->id)
            ->has('organizations', 0)
            ->has('orderStatuses', count(OrderStatus::cases()))
            ->where('supportedVariables.0', '{customer_name}')
            ->where('supportedVariables.5', '{weight}')
            ->where('supportedVariables.6', '{total}'));

    $this->actingAs($owner)->patch(route('cms.message-templates.update', $organizationTemplate), [
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Washing->value,
        'body' => 'Halo {customer_name}, pesanan {order_number} sedang dicuci di {branch_name}.',
        'is_active' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $template = MessageTemplate::query()
        ->where('organization_id', $organization->id)
        ->where('scope_key', 'branch:'.$branch->id)
        ->firstOrFail();

    expect($template->branch_id)->toBe($branch->id)
        ->and($template->scope_key)->toBe('branch:'.$branch->id)
        ->and($template->order_status)->toBe(OrderStatus::Washing)
        ->and($template->is_active)->toBeFalse()
        ->and($organizationTemplate->fresh()?->branch_id)->toBeNull();

    $this->actingAs($owner)->patch(route('cms.message-templates.update', $template), [
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Washing->value,
        'body' => 'Pesanan {order_number} milik {name} seberat {weight} total {total} siap diambil di {branch_name}.',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($template->fresh()?->scope_key)->toBe('branch:'.$branch->id)
        ->and($template->fresh()?->branch_id)->toBe($branch->id)
        ->and($template->fresh()?->is_active)->toBeTrue();

    $this->actingAs($owner)->patch(route('cms.message-templates.update', $otherTemplate), [
        'branch_id' => null,
        'order_status' => OrderStatus::Completed->value,
        'body' => 'Selesai.',
    ])->assertForbidden();
});

it('lets superadmins select an organization and see all templates', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $superadmin = User::factory()->create();
    $superadmin->assignRole(RoleName::Superadmin->value);

    $this->actingAs($superadmin)->post(route('cms.message-templates.store'), [
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Completed->value,
        'body' => 'Terima kasih {customer_name}. Lacak: {tracking_url}',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($superadmin)
        ->get(route('cms.message-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('messageTemplates', 1)
            ->has('organizations', 1)
            ->where('messageTemplates.0.organization_id', $organization->id));
});

it('rejects duplicate scope status, cross-organization branches, and invalid variables', function (): void {
    $organization = Organization::factory()->create();
    $otherBranch = Branch::factory()->forOrganization()->create();
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    MessageTemplate::factory()->for($organization)->create([
        'order_status' => OrderStatus::Received,
        'scope_key' => 'organization',
    ]);

    $route = route('cms.message-templates.store');

    $this->actingAs($owner)->post($route, [
        'branch_id' => null,
        'order_status' => OrderStatus::Received->value,
        'body' => 'Halo {customer_name}',
    ])->assertSessionHasErrors('order_status');

    $this->actingAs($owner)->post($route, [
        'branch_id' => $otherBranch->id,
        'order_status' => OrderStatus::Washing->value,
        'body' => 'Halo {customer_name}',
    ])->assertSessionHasErrors('branch_id');

    $this->actingAs($owner)->post($route, [
        'branch_id' => null,
        'order_status' => OrderStatus::Washing->value,
        'body' => 'Halo {unknown} dan {customer_name',
    ])->assertSessionHasErrors('body');

    $this->assertDatabaseCount('message_templates', 1);
});

it('requires exact typed confirmation before deleting a template', function (): void {
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create();
    $template = MessageTemplate::factory()->forBranch($branch)->create([
        'order_status' => OrderStatus::Drying,
    ]);
    $owner = User::factory()->for($organization)->create();
    $owner->assignRole(RoleName::Owner->value);
    $route = route('cms.message-templates.destroy', $template);

    $this->actingAs($owner)->delete($route, [
        'confirmation' => 'salah',
    ])->assertSessionHasErrors('confirmation');

    $this->assertDatabaseHas('message_templates', ['id' => $template->id]);

    $this->actingAs($owner)->delete($route, [
        'confirmation' => $template->deletionConfirmation(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('message_templates', ['id' => $template->id]);
});

it('rejects unauthenticated and unauthorized template management', function (): void {
    $organization = Organization::factory()->create();
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);

    $this->get(route('cms.message-templates.index'))->assertRedirect(route('login'));
    $this->actingAs($customer)
        ->withSession(['customer_session_version' => $customer->session_version])
        ->get(route('cms.message-templates.index'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->withSession(['customer_session_version' => $customer->session_version])
        ->post(route('cms.message-templates.store'), [
            'organization_id' => $organization->id,
            'order_status' => OrderStatus::Received->value,
            'body' => 'Halo.',
        ])->assertForbidden();
});
