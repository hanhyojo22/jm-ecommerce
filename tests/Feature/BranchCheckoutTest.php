<?php

namespace Tests\Feature;

use App\Actions\UpdateSellerOrderStatus;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BranchCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_an_order_takes_stock_from_the_chosen_branch_and_a_cancel_puts_it_back_there(): void
    {
        [$makati, $cebu, $product] = $this->pharmacyWithStock(makati: 5, cebu: 3);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.branch.id', $cebu->id);
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertCreated();

        $sellerOrder = SellerOrder::query()->sole();
        $this->assertSame($cebu->id, $sellerOrder->store_id);
        $this->assertSame([5, 1], [$this->onHand($makati, $product), $this->onHand($cebu, $product)]);
        $this->assertSame(6, $product->fresh()->stock_quantity);

        app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'cancelled', null);

        $this->assertSame([5, 3], [$this->onHand($makati, $product), $this->onHand($cebu, $product)]);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_a_branch_cannot_sell_more_than_it_has_even_when_another_branch_has_plenty(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 50, cebu: 1);

        $this->actingAs($this->buyer())->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 2])
            ->assertUnprocessable();
    }

    public function test_closing_the_first_branch_does_not_stop_buying_at_another(): void
    {
        [$makati, $cebu, $product] = $this->pharmacyWithStock(makati: 5, cebu: 4);
        $makati->update(['status' => 'inactive']);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertCreated();

        $this->assertSame($cebu->id, SellerOrder::query()->sole()->store_id);
    }

    public function test_the_app_must_choose_a_branch_before_its_first_item(): void
    {
        [, , $product] = $this->pharmacyWithStock(makati: 5, cebu: 5);

        $this->actingAs($this->buyer())->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.branch.0', 'The branch field is required.');
    }

    public function test_checkout_is_refused_once_the_carts_branch_closes(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 5, cebu: 5);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $cebu->update(['status' => 'inactive']);

        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $this->onHand($cebu, $product));
    }

    /** @return array{0: Store, 1: Store, 2: Product} */
    private function pharmacyWithStock(int $makati, int $cebu): array
    {
        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active']);
        $branch = fn (string $city): Store => Store::create([
            'tenant_id' => $tenant->id, 'name' => "Botika Plus {$city}", 'slug' => 'botika-plus-'.strtolower($city),
            'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => $city, 'status' => 'active',
        ]);
        [$makatiBranch, $cebuBranch] = [$branch('Makati'), $branch('Cebu')];
        $product = Product::create([
            'tenant_id' => $tenant->id, 'store_id' => $makatiBranch->id, 'name' => 'Paracetamol 500mg', 'slug' => 'paracetamol-500mg',
            'sku' => 'PARA-500', 'price' => '6.50', 'stock_quantity' => $makati + $cebu, 'status' => 'active',
        ]);
        foreach ([[$makatiBranch, $makati], [$cebuBranch, $cebu]] as [$store, $quantity]) {
            BranchStock::create(['store_id' => $store->id, 'product_id' => $product->id, 'variant_key' => 'default', 'quantity' => $quantity]);
        }

        return [$makatiBranch, $cebuBranch, $product];
    }

    private function onHand(Store $branch, Product $product): int
    {
        return (int) BranchStock::query()->where('store_id', $branch->id)->where('product_id', $product->id)->value('quantity');
    }

    private function buyer(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** @return array<string, string> */
    private function delivery(): array
    {
        return ['recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '100 Colon Street', 'city' => 'Cebu', 'province' => 'Cebu', 'postal_code' => '6000'];
    }
}
