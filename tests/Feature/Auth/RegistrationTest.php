<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'whatsapp_phone' => '0812-3456-7890',
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ]);

        $this->assertAuthenticated();
        $response
            ->assertRedirect(route('mobile.app', absolute: false))
            ->assertSessionHas('customer_session_version', 1);

        $user = User::query()->where('whatsapp_phone', '6281234567890')->firstOrFail();

        $this->assertNull($user->email);
        $this->assertNull($user->password);
        $this->assertNull($user->organization_id);
        $this->assertTrue(Hash::check('123456', $user->pin_hash));
        $this->assertTrue($user->hasRole(RoleName::Customer->value));
        $this->assertFalse($user->must_change_pin);
        $this->assertSame(1, $user->session_version);
        $this->assertCount(0, $user->customerOrganizations);
    }

    public function test_phone_variants_are_normalized_before_uniqueness_validation(): void
    {
        User::factory()->create(['whatsapp_phone' => '6281234567890']);

        $this->post(route('register.store'), [
            'name' => 'Duplicate Customer',
            'whatsapp_phone' => '+62 812-3456-7890',
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ])->assertSessionHasErrors('whatsapp_phone');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_requires_a_confirmed_six_digit_pin(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Test Customer',
            'whatsapp_phone' => '081234567890',
            'pin' => '12345',
            'pin_confirmation' => '999999',
        ])->assertSessionHasErrors('pin');

        $this->assertGuest();
    }
}
