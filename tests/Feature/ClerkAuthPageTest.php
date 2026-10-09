<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkAuthPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_clerk_sign_in_and_sign_up_pages_are_available(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-clerk-component="sign-in"', false);
        $this->get(route('register'))->assertOk()->assertSee('data-clerk-component="sign-up"', false);
    }

    public function test_seller_registration_starts_with_clerk_then_marketplace_onboarding(): void
    {
        $this->get(route('seller.register'))->assertRedirect('/sign-up?redirect=/start-selling');
    }

    public function test_sign_up_rejects_protocol_relative_redirects(): void
    {
        $this->get(route('register', ['redirect' => '//evil.example']))
            ->assertStatus(422);
    }

    public function test_sign_in_ignores_malformed_product_add_intents(): void
    {
        $this->get(route('login', ['add_to_cart' => ['unexpected']]))
            ->assertOk()
            ->assertSessionMissing('pending_cart_addition');
    }

    public function test_legacy_breeze_authentication_pages_are_not_available(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/reset-password/example-token')->assertNotFound();
        $this->get('/verify-email')->assertNotFound();
        $this->get('/confirm-password')->assertNotFound();
    }
}
