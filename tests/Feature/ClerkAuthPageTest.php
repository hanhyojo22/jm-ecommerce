<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_with_the_web_store_closed_sign_up_opens_only_for_an_invitation(): void
    {
        config(['marketplace.web_store.enabled' => false]);

        $this->get(route('register'))->assertNotFound();
        $this->get(route('register', ['__clerk_ticket' => 'ticket-123', '__clerk_status' => 'sign_up']))
            ->assertOk()
            ->assertSee('data-clerk-component="sign-up"', false);
    }

    public function test_an_invitation_opens_the_sign_up_page_even_in_a_signed_in_browser(): void
    {
        config(['marketplace.web_store.enabled' => false]);
        $owner = User::factory()->seller()->create();

        // The page itself asks Clerk who is signed in and offers to sign them out (clerk-invitation.js).
        $this->actingAs($owner)->get('/sign-up?__clerk_ticket=ticket-123&__clerk_status=sign_up')
            ->assertOk()
            ->assertSee('data-clerk-component="sign-up"', false);
        $this->actingAs($owner)->get(route('register'))->assertNotFound();
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
