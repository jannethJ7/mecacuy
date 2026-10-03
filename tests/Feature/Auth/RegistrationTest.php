<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.registration_enabled' => true]);
    }

    public function test_registration_is_closed_when_disabled(): void
    {
        config(['auth.registration_enabled' => false]);
        $this->get('/register')->assertForbidden();
        $this->post('/register', [
            'name' => 'Intruso', 'email' => 'closed@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'closed@example.com']);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
