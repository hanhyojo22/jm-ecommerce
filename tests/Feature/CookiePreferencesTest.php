<?php

namespace Tests\Feature;

use App\Support\CookiePreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookiePreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_pages_and_banner_are_public(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Your privacy matters')->assertSee('Cookie Preferences');
        $this->get(route('privacy-policy'))->assertOk()->assertSee('Privacy Policy');
        $this->get(route('cookie-policy'))->assertOk()->assertSee('Strictly Necessary');
    }

    public function test_visitor_can_save_and_change_functional_consent(): void
    {
        $this->post(route('cookie-preferences.store'), [
            'functional' => 1,
            'analytics' => 0,
            'marketing' => 0,
        ])->assertOk()->assertCookie(CookiePreferences::COOKIE_NAME);

        $this->withCookie(CookiePreferences::COOKIE_NAME, json_encode([
            'version' => CookiePreferences::VERSION,
            'functional' => true,
            'analytics' => false,
            'marketing' => false,
        ]))->get(route('home'))->assertOk()->assertDontSee('data-cookie-banner');

        $this->withSession(['marketplace.recently_viewed' => [123], 'marketplace.recent_searches' => ['lamp']])
            ->post(route('cookie-preferences.store'), [
                'functional' => 0,
                'analytics' => 0,
                'marketing' => 0,
            ])->assertOk()->assertSessionMissing('marketplace.recently_viewed')->assertSessionMissing('marketplace.recent_searches');
    }

    public function test_inactive_tracking_cannot_be_enabled_and_old_policy_needs_new_consent(): void
    {
        $this->post(route('cookie-preferences.store'), [
            'functional' => 1,
            'analytics' => 1,
            'marketing' => 0,
        ])->assertSessionHasErrors('analytics');

        $this->withCookie(CookiePreferences::COOKIE_NAME, json_encode([
            'version' => 1,
            'functional' => true,
            'analytics' => false,
            'marketing' => false,
        ]))->get(route('home'))->assertOk()->assertSee('data-cookie-banner');
    }
}
