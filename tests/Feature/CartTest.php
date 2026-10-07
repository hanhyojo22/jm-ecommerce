<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_badge_displays_the_total_number_of_units_after_adding_a_product(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 2])
            ->assertRedirect(route('cart.index'));

        $this->actingAs($buyer)->get(route('cart.index'))
            ->assertSee('2 items')
            ->assertSee('Cart (2)')
            ->assertSee('aria-label="Cart: 2 items"', false);

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1])
            ->assertRedirect(route('cart.index'));

        $this->actingAs($buyer)->get(route('cart.index'))
            ->assertSee('3 items')
            ->assertSee('Cart (3)')
            ->assertSee('aria-label="Cart: 3 items"', false);
    }

    public function test_buyer_can_update_a_cart_item_quantity_and_sees_confirmation(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1]);
        $cartItem = $buyer->cart()->firstOrFail()->items()->firstOrFail();

        $this->actingAs($buyer)->patch(route('cart.update', $cartItem), ['quantity' => 3])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'Cart updated.');

        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id, 'quantity' => 3]);
    }

    public function test_buyer_can_remove_a_cart_item_and_sees_confirmation(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1]);
        $cartItem = $buyer->cart()->firstOrFail()->items()->firstOrFail();

        $this->actingAs($buyer)->delete(route('cart.destroy', $cartItem))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'Item removed.');

        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }

    public function test_buyer_can_restore_a_removed_cart_item(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 2]);
        $cartItem = $buyer->cart()->firstOrFail()->items()->firstOrFail();

        $this->actingAs($buyer)
            ->delete(route('cart.destroy', $cartItem))
            ->assertSessionHas('removed_cart_item', ['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($buyer)
            ->post(route('cart.restore'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'Item restored to your cart.');

        $this->assertDatabaseHas('cart_items', ['cart_id' => $buyer->cart()->firstOrFail()->id, 'product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_buyer_cannot_update_another_buyers_cart_item(): void
    {
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1]);
        $cartItem = $buyer->cart()->firstOrFail()->items()->firstOrFail();

        $this->actingAs($otherBuyer)
            ->patch(route('cart.update', $cartItem), ['quantity' => 2])
            ->assertForbidden();

        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id, 'quantity' => 1]);
    }

    public function test_quantity_update_rejects_a_product_from_an_inactive_store(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1]);
        $product->store->update(['status' => 'inactive']);
        $cartItem = $buyer->cart()->firstOrFail()->items()->firstOrFail();

        $this->actingAs($buyer)
            ->from(route('cart.index'))
            ->patch(route('cart.update', $cartItem), ['quantity' => 2])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id, 'quantity' => 1]);
    }

    private function product(): Product
    {
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind', 'status' => 'active']);
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Goods',
            'slug' => 'northwind-goods',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);

        return Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Canvas Tote',
            'slug' => 'canvas-tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);
    }
}
