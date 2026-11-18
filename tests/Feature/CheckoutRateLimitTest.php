<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckoutRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private Store $branch;

    private Product $paracetamol;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active', 'delivery_fee' => '49.00']);
        $this->branch = Store::create([
            'tenant_id' => $tenant->id, 'name' => 'Botika Plus Cebu', 'slug' => 'botika-plus-cebu',
            'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => 'Cebu', 'status' => 'active',
        ]);
        $this->paracetamol = Product::create([
            'tenant_id' => $tenant->id, 'store_id' => $this->branch->id, 'name' => 'Paracetamol 500mg', 'slug' => 'paracetamol-500mg',
            'sku' => 'PARA-500', 'price' => '7.00', 'stock_quantity' => 100, 'status' => 'active',
        ]);
        BranchStock::create(['store_id' => $this->branch->id, 'product_id' => $this->paracetamol->id, 'variant_key' => 'default', 'quantity' => 100]);
    }

    public function test_refused_checkouts_do_not_use_up_the_hourly_order_limit(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        foreach (range(1, 12) as $attempt) {
            $this->actingAs($buyer)->postJson('/api/v1/checkout', ['payment_method' => 'cod'])->assertUnprocessable();
        }

        $this->placeOrder($buyer)->assertCreated();
    }

    public function test_placed_orders_still_count_toward_the_hourly_limit(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        foreach (range(1, 10) as $order) {
            $this->placeOrder($buyer)->assertCreated();
        }

        $this->placeOrder($buyer)->assertTooManyRequests();
    }

    public function test_a_blocked_customer_is_told_how_long_to_wait(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        foreach (range(1, 30) as $attempt) {
            $this->actingAs($buyer)->postJson('/api/v1/checkout', ['payment_method' => 'cod'])->assertUnprocessable();
        }

        $this->actingAs($buyer)->postJson('/api/v1/checkout', ['payment_method' => 'cod'])
            ->assertTooManyRequests()
            ->assertJsonPath('error.message', 'Too many tries. Try again in 60 minutes.');
        $this->actingAs($buyer)->post(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertTooManyRequests()
            ->assertSee('Too many tries were sent. Try again in 60 minutes.');
    }

    private function placeOrder(User $buyer): TestResponse
    {
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $this->branch->id, 'product_id' => $this->paracetamol->id, 'quantity' => 1])->assertCreated();

        return $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '100 Colon Street', 'city' => 'Cebu',
            'delivery_latitude' => 10.3157, 'delivery_longitude' => 123.8854, 'payment_method' => 'cod',
        ]);
    }
}
