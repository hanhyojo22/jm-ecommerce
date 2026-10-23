<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_moderator_role_cannot_access_platform_admin_routes(): void
    {
        $moderator = User::factory()->create([
            'account_type' => 'admin',
            'platform_role' => 'moderator',
            'email_verified_at' => now(),
        ]);

        $this->assertForbiddenForAllAdminSurfaces($moderator);
    }

    public function test_an_administrator_without_a_platform_role_cannot_access_platform_admin_routes(): void
    {
        $administrator = User::factory()->create([
            'account_type' => 'admin',
            'platform_role' => null,
            'email_verified_at' => now(),
        ]);

        $this->assertForbiddenForAllAdminSurfaces($administrator);
    }

    public function test_a_platform_administrator_can_access_platform_admin_routes(): void
    {
        $administrator = User::factory()->administrator()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($administrator)->get(route('admin.chat-reports.index'))->assertOk();
        $this->actingAs($administrator)->get(route('admin.order-issues.index'))->assertOk();
        $this->actingAs($administrator)->get(route('dashboard'))->assertOk();
        $this->actingAs($administrator)->get(route('admin.moderation'))->assertOk();
    }

    private function assertForbiddenForAllAdminSurfaces(User $user): void
    {
        $this->actingAs($user)->get(route('admin.chat-reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.order-issues.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.moderation'))->assertForbidden();
    }
}
