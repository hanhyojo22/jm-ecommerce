<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_caller_cannot_reset_their_limit_by_changing_the_forwarded_ip(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        foreach (range(1, 30) as $attempt) {
            $this->actingAs($buyer)
                ->withHeader('X-Forwarded-For', "203.0.113.{$attempt}")
                ->getJson(route('api.v1.cart.show'))
                ->assertOk();
        }

        $this->actingAs($buyer)
            ->withHeader('X-Forwarded-For', '203.0.113.99')
            ->getJson(route('api.v1.cart.show'))
            ->assertTooManyRequests();
    }
}
