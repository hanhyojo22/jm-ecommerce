<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_register_a_device_token(): void
    {
        $this->postJson(route('api.v1.device-tokens.store'), ['token' => 'tok-1'])
            ->assertUnauthorized();
    }

    public function test_a_buyer_registers_a_device_token(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)
            ->postJson(route('api.v1.device-tokens.store'), ['token' => 'tok-1', 'platform' => 'android'])
            ->assertNoContent(201);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $buyer->id,
            'token' => 'tok-1',
            'platform' => 'android',
        ]);
    }

    public function test_registering_an_existing_token_repoints_it_to_the_signed_in_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        DeviceToken::create(['user_id' => $first->id, 'token' => 'shared-device', 'platform' => 'android']);

        $this->actingAs($second)
            ->postJson(route('api.v1.device-tokens.store'), ['token' => 'shared-device'])
            ->assertNoContent(201);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['token' => 'shared-device', 'user_id' => $second->id]);
    }

    public function test_a_user_deletes_their_own_token(): void
    {
        $buyer = User::factory()->create();
        DeviceToken::create(['user_id' => $buyer->id, 'token' => 'tok-1', 'platform' => 'android']);

        $this->actingAs($buyer)
            ->deleteJson(route('api.v1.device-tokens.destroy'), ['token' => 'tok-1'])
            ->assertNoContent();

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_a_user_cannot_delete_someone_elses_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        DeviceToken::create(['user_id' => $owner->id, 'token' => 'tok-1', 'platform' => 'android']);

        $this->actingAs($other)
            ->deleteJson(route('api.v1.device-tokens.destroy'), ['token' => 'tok-1'])
            ->assertNoContent();

        $this->assertDatabaseCount('device_tokens', 1);
    }
}
