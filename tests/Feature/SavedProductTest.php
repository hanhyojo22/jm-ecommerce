<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_saves_and_removes_a_product_on_the_website(): void
    {
        [, $product] = $this->pharmacy();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $productUrl = route('catalog.show', [$product->store->slug, $product->slug]);

        $this->actingAs($buyer)->get($productUrl)->assertSee('Save for later');
        $this->from($productUrl)->put(route('saved-products.store', $product))->assertRedirect($productUrl);
        $this->put(route('saved-products.store', $product));

        $this->assertSame(1, $buyer->savedProducts()->count());
        $this->get($productUrl)->assertSee('aria-pressed="true"', false)->assertDontSee('Save for later');
        $this->get(route('saved-products.index'))->assertOk()->assertSee('Paracetamol 500mg')->assertSee('Add to cart');

        $this->delete(route('saved-products.destroy', $product));
        $this->get(route('saved-products.index'))->assertSee('Nothing saved yet');
    }

    public function test_the_app_lists_saved_products_with_the_branch_stock_even_when_it_is_out(): void
    {
        [$branch, $product] = $this->pharmacy();
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($buyer)->postJson(route('api.v1.saved-products.store', $product))->assertCreated();
        BranchStock::query()->where('product_id', $product->id)->update(['quantity' => 0]);

        $this->getJson(route('api.v1.saved-products.index', ['branch' => $branch->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.in_stock', false);

        $this->deleteJson(route('api.v1.saved-products.destroy', $product))->assertOk();
        $this->getJson(route('api.v1.saved-products.index'))->assertJsonCount(0, 'data');
    }

    public function test_the_website_list_shows_the_cart_branchs_stock(): void
    {
        [$branch, $product] = $this->pharmacy();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $buyer->savedProducts()->attach($product);
        $buyer->cart()->create(['store_id' => $branch->id]);
        BranchStock::query()->where('product_id', $product->id)->update(['quantity' => 0]);

        $this->actingAs($buyer)->get(route('saved-products.index'))
            ->assertSee('Out of stock')
            ->assertDontSee('Add Paracetamol 500mg to cart');
    }

    public function test_an_unpublished_product_cannot_be_saved_and_drops_off_the_list(): void
    {
        [, $product] = $this->pharmacy();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->postJson(route('api.v1.saved-products.store', $product))->assertCreated();

        $product->update(['status' => 'draft']);

        $this->postJson(route('api.v1.saved-products.store', $product))->assertNotFound();
        $this->getJson(route('api.v1.saved-products.index'))->assertJsonCount(0, 'data');
        $this->get(route('saved-products.index'))->assertDontSee('Paracetamol 500mg');
    }

    /**
     * @return array{Store, Product}
     */
    private function pharmacy(): array
    {
        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active']);
        $branch = Store::create([
            'tenant_id' => $tenant->id, 'name' => 'Botika Plus Cebu', 'slug' => 'botika-plus-cebu',
            'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => 'Cebu', 'status' => 'active',
        ]);
        $product = Product::create([
            'tenant_id' => $tenant->id, 'store_id' => $branch->id, 'name' => 'Paracetamol 500mg', 'slug' => 'paracetamol-500mg',
            'sku' => 'PARA-500', 'price' => '6.50', 'stock_quantity' => 5, 'status' => 'active',
        ]);
        BranchStock::create(['store_id' => $branch->id, 'product_id' => $product->id, 'variant_key' => 'default', 'quantity' => 5]);

        return [$branch, $product];
    }
}
