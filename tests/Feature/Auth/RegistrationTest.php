<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_buyer_registration_creates_a_buyer_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'account_type' => 'buyer']);
    }

    public function test_seller_registration_creates_a_seller_account(): void
    {
        $response = $this->post(route('seller.register.store'), [
            'name' => 'Seller User',
            'email' => 'seller@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'seller@example.com', 'account_type' => 'seller']);
    }
}
