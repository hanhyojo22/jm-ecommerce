<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_user_update_synchronizes_a_linked_local_user(): void
    {
        config()->set('services.clerk.webhook_signing_secret', 'whsec_'.base64_encode('test-secret'));
        $user = User::factory()->create(['clerk_user_id' => 'user_123', 'email' => 'old@example.com', 'name' => 'Old Name']);
        $payload = json_encode([
            'type' => 'user.updated',
            'data' => [
                'id' => 'user_123',
                'first_name' => 'New',
                'last_name' => 'Name',
                'primary_email_address_id' => 'email_123',
                'email_addresses' => [[
                    'id' => 'email_123',
                    'email_address' => 'new@example.com',
                    'verification' => ['status' => 'verified'],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload)->assertOk();

        $this->assertSame('new@example.com', $user->refresh()->email);
        $this->assertSame('New Name', $user->name);
    }

    public function test_an_unsigned_webhook_is_rejected(): void
    {
        $this->postJson(route('clerk.webhook'), ['type' => 'user.deleted', 'data' => ['id' => 'user_123']])
            ->assertUnauthorized();
    }

    public function test_a_signed_unverified_email_update_clears_the_local_verification_state(): void
    {
        config()->set('services.clerk.webhook_signing_secret', 'whsec_'.base64_encode('test-secret'));
        $user = User::factory()->create(['clerk_user_id' => 'user_123', 'email_verified_at' => now()]);
        $payload = json_encode([
            'type' => 'user.updated',
            'data' => [
                'id' => 'user_123',
                'primary_email_address_id' => 'email_123',
                'email_addresses' => [[
                    'id' => 'email_123',
                    'email_address' => 'new@example.com',
                    'verification' => ['status' => 'unverified'],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
            'email_verified_at' => null,
        ]);
    }

    public function test_a_signed_deletion_deactivates_without_deleting_the_local_user(): void
    {
        config()->set('services.clerk.webhook_signing_secret', 'whsec_'.base64_encode('test-secret'));
        $user = User::factory()->create(['clerk_user_id' => 'user_123', 'email_verified_at' => now()]);
        $payload = json_encode(['type' => 'user.deleted', 'data' => ['id' => 'user_123']], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload)->assertOk();

        $this->assertNotNull($user->fresh());
        $this->assertFalse($user->fresh()->is_active);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    private function postWebhook(string $payload)
    {
        $id = 'msg_123';
        $timestamp = (string) now()->getTimestamp();
        $signature = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$payload, 'test-secret', true));

        return $this->call('POST', route('clerk.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            'HTTP_SVIX_SIGNATURE' => 'v1,'.$signature,
        ], $payload);
    }
}
