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

        $this->actingAs($buyer)->postJson(route('cart.store', $product), ['quantity' => 1])
            ->assertOk()
            ->assertJson(['cart_count' => 3]);

        $this->actingAs($buyer)->get(route('cart.index'))
            ->assertSee('3 items')
            ->assertSee('Cart (3)')
            ->assertSee('aria-label="Cart: 3 items"', false);
    }

    public function test_cart_asks_for_a_new_choice_when_a_product_gains_variants(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1])
            ->assertRedirect(route('cart.index'));

        $product->variants()->create(['color' => 'Navy', 'size' => 'M', 'sku' => 'NAVY-M', 'stock_quantity' => 5]);
        $this->actingAs($buyer)->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Choose a color and size again');
    }

    public function test_cart_labels_a_product_sold_out_after_it_was_added(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1]);
        $product->update(['stock_quantity' => 0]);

        $this->actingAs($buyer)->get(route('cart.index'))
            ->assertSee('Sold out')
            ->assertDontSee('Only 0 available');
    }

    public function test_buyer_cannot_add_a_sold_out_product_to_the_cart(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $product->update(['stock_quantity' => 0]);

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('cart_items', 0);
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
            ->assertNotFound();

        $this->actingAs($otherBuyer)
            ->delete(route('cart.destroy', $cartItem))
            ->assertNotFound();

        $this->actingAs($otherBuyer)
            ->patch(route('cart.update', $cartItem), ['quantity' => 0])
            ->assertNotFound();

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

    public function test_items_are_selected_by_default_when_added_to_the_cart(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1]);

        $this->assertTrue($buyer->cart->items()->first()->selected);
    }

    public function test_updating_the_selection_persists_only_the_given_items(): void
    {
        $buyer = User::factory()->create();
        $first = $this->product();
        $second = $this->product('canvas-tote-two', 'TOTE-002');
        $this->actingAs($buyer)->post(route('cart.store', $first), ['quantity' => 1]);
        $this->actingAs($buyer)->post(route('cart.store', $second), ['quantity' => 1]);
        $keptItem = $buyer->cart->items()->where('product_id', $first->id)->first();

        $this->actingAs($buyer)
            ->patchJson(route('cart.selection'), ['selected' => [$keptItem->id]])
            ->assertOk()
            ->assertJson(['selected_count' => 1, 'subtotal' => '299.00']);

        $this->assertTrue($keptItem->refresh()->selected);
        $this->assertFalse($buyer->cart->items()->where('product_id', $second->id)->first()->selected);
    }

    public function test_the_selection_cannot_be_set_to_another_buyers_cart_item(): void
    {
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $product = $this->product();
        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1]);
        $this->actingAs($otherBuyer)->post(route('cart.store', $product), ['quantity' => 1]);
        $otherItem = $otherBuyer->cart->items()->first();

        $this->actingAs($buyer)
            ->patchJson(route('cart.selection'), ['selected' => [$otherItem->id]])
            ->assertOk()
            ->assertJson(['selected_count' => 0]);

        $this->assertTrue($otherItem->refresh()->selected);
        $this->assertFalse($buyer->cart->items()->first()->selected);
    }

    public function test_removing_selected_items_leaves_the_unselected_ones(): void
    {
        $buyer = User::factory()->create();
        $first = $this->product();
        $second = $this->product('canvas-tote-two', 'TOTE-002');
        $this->actingAs($buyer)->post(route('cart.store', $first), ['quantity' => 1]);
        $this->actingAs($buyer)->post(route('cart.store', $second), ['quantity' => 1]);
        $keptItem = $buyer->cart->items()->where('product_id', $second->id)->first();
        $keptItem->update(['selected' => false]);

        $this->actingAs($buyer)->delete(route('cart.destroy-selected'))
            ->assertRedirect(route('cart.index'));

        $this->assertSame(1, $buyer->cart->items()->count());
        $this->assertSame($second->id, $buyer->cart->items()->first()->product_id);
    }

    public function test_the_summary_totals_only_the_selected_items(): void
    {
        $buyer = User::factory()->create();
        $first = $this->product();
        $second = $this->product('canvas-tote-two', 'TOTE-002');
        $this->actingAs($buyer)->post(route('cart.store', $first), ['quantity' => 1]);
        $this->actingAs($buyer)->post(route('cart.store', $second), ['quantity' => 1]);
        $buyer->cart->items()->where('product_id', $second->id)->update(['selected' => false]);

        $this->actingAs($buyer)->get(route('cart.index'))
            ->assertSee('of 2 selected')
            ->assertSee('299.00')
            ->assertDontSee('598.00');
    }

    public function test_an_item_that_sold_out_while_selected_is_unselected_when_the_cart_is_viewed(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 2]);
        $product->update(['stock_quantity' => 1]);

        $this->actingAs($buyer)->get(route('cart.index'))->assertSee('Only 1 available');

        $this->assertFalse($buyer->cart->items()->first()->selected);
    }

    private function product(string $slug = 'canvas-tote', string $sku = 'TOTE-001'): Product
    {
        $tenant = Tenant::create(['name' => 'Northwind '.$sku, 'slug' => 'northwind-'.str()->lower($sku), 'status' => 'active']);
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Goods',
            'slug' => 'northwind-goods-'.str()->lower($sku),
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);

        return Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Canvas Tote',
            'slug' => $slug,
            'sku' => $sku,
            'price' => '299.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);
    }
}
