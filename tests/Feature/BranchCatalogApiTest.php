<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_branch_catalog_lists_only_what_that_branch_has_on_hand(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $paracetamol = $this->product($tenant, $makati, 'Paracetamol 500mg', ['generic_name' => 'Paracetamol', 'requires_prescription' => false]);
        $amoxicillin = $this->product($tenant, $makati, 'Amoxicillin 500mg', ['requires_prescription' => true]);
        $cetirizine = $this->product($tenant, $makati, 'Cetirizine 10mg');
        $this->stock($makati, $paracetamol, 30);
        $this->stock($makati, $cetirizine, 0);
        $this->stock($cebu, $amoxicillin, 8);

        $this->getJson(route('api.v1.products.index', ['branch' => $makati->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paracetamol->id)
            ->assertJsonPath('data.0.stock_quantity', 30)
            ->assertJsonPath('data.0.generic_name', 'Paracetamol')
            ->assertJsonPath('data.0.requires_prescription', false);

        $this->getJson(route('api.v1.products.index', ['branch' => $cebu->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $amoxicillin->id)
            ->assertJsonPath('data.0.requires_prescription', true);
    }

    public function test_search_suggestions_count_exactly_what_the_branch_search_lists(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $painRelief = Category::create(['name' => 'Pain & Fever', 'slug' => 'pain-fever', 'is_active' => true]);
        $tablet = $this->product($tenant, $makati, 'Paracetamol 500mg Tablet', ['generic_name' => 'Paracetamol', 'category_id' => $painRelief->id]);
        $biogesic = $this->product($tenant, $makati, 'Biogesic 500mg', ['generic_name' => 'Paracetamol', 'category_id' => $painRelief->id]);
        $combination = $this->product($tenant, $makati, 'Decolgen Tablet', ['generic_name' => 'Phenylephrine + Paracetamol', 'requires_prescription' => true]);
        $syrup = $this->product($tenant, $makati, 'Paracetamol Syrup', ['generic_name' => 'Paracetamol']);
        foreach ([$tablet, $biogesic, $combination] as $product) {
            $this->stock($makati, $product, 10);
        }
        $this->stock($cebu, $syrup, 4);

        $this->getJson(route('api.v1.products.suggestions', ['q' => 'para', 'branch' => $makati->id]))
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('names.0', ['name' => 'Paracetamol', 'count' => 2])
            ->assertJsonPath('names.1', ['name' => 'Phenylephrine + Paracetamol', 'count' => 1])
            ->assertJsonPath('categories', [['slug' => 'pain-fever', 'name' => 'Pain & Fever', 'count' => 2]])
            ->assertJsonPath('items.0.at_branch', true)
            ->assertJsonPath('items.3.product_id', $syrup->id)
            ->assertJsonPath('items.3.at_branch', false)
            ->assertJsonPath('items.3.generic_name', 'Paracetamol');

        $this->getJson(route('api.v1.products.index', ['q' => 'para', 'branch' => $makati->id]))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->getJson(route('api.v1.products.index', ['q' => 'para', 'branch' => $makati->id, 'no_prescription' => 'true']))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissing(['id' => $combination->id]);
    }

    public function test_a_closed_branch_is_not_found(): void
    {
        [, $makati] = $this->pharmacy();
        $makati->update(['status' => 'inactive']);

        $this->getJson(route('api.v1.products.index', ['branch' => $makati->id]))->assertNotFound();
    }

    public function test_closing_the_first_branch_keeps_the_medicines_visible_at_the_others(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $paracetamol = $this->product($tenant, $makati, 'Paracetamol 500mg');
        $this->stock($cebu, $paracetamol, 5);
        $makati->update(['status' => 'inactive']);

        $this->getJson(route('api.v1.products.index', ['branch' => $cebu->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $paracetamol->id);
    }

    public function test_product_detail_suggests_only_what_the_branch_has_on_hand(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $paracetamol = $this->product($tenant, $makati, 'Paracetamol 500mg');
        $cetirizine = $this->product($tenant, $makati, 'Cetirizine 10mg');
        $loratadine = $this->product($tenant, $makati, 'Loratadine 10mg');
        $this->stock($cebu, $paracetamol, 5);
        $this->stock($cebu, $cetirizine, 3);
        $this->stock($makati, $loratadine, 9);

        $response = $this->getJson(route('api.v1.products.show', [$makati->slug, $paracetamol->slug, 'branch' => $cebu->id]))->assertOk();

        $this->assertSame([$cetirizine->id], array_column($response->json('data.related_products'), 'id'));
        $this->assertSame([$cetirizine->id], array_column($response->json('data.more_from_store'), 'id'));
        $this->assertSame((string) $cebu->slug, $response->json('data.branch.slug'));
    }

    public function test_product_detail_reports_the_branchs_own_counts_per_variant(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $syrup = $this->product($tenant, $makati, 'Ascorbic Acid Syrup');
        $small = ProductVariant::create(['product_id' => $syrup->id, 'size' => '60 ml', 'sku' => 'ASC-60', 'stock_quantity' => 99]);
        $large = ProductVariant::create(['product_id' => $syrup->id, 'size' => '120 ml', 'sku' => 'ASC-120', 'stock_quantity' => 99]);
        $this->stock($cebu, $syrup, 4, $small);

        $response = $this->getJson(route('api.v1.products.show', [$makati->slug, $syrup->slug, 'branch' => $cebu->id]))
            ->assertOk()
            ->assertJsonPath('data.branch.id', $cebu->id)
            ->assertJsonPath('data.stock_quantity', 4);

        $variants = collect($response->json('data.variants'))->keyBy('id');
        $this->assertSame([4, true], [$variants[$small->id]['stock_quantity'], $variants[$small->id]['in_stock']]);
        $this->assertSame([0, false], [$variants[$large->id]['stock_quantity'], $variants[$large->id]['in_stock']]);

        $this->getJson(route('api.v1.products.show', [$makati->slug, $syrup->slug, 'branch' => $makati->id]))
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 0)
            ->assertJsonPath('data.in_stock', false);
    }

    public function test_availability_lists_open_stocked_branches_nearest_first(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $taguig = $this->branch($tenant, 'Taguig', 14.5176, 121.0509);
        $closed = $this->branch($tenant, 'Pasig', 14.5764, 121.0851);
        $closed->update(['status' => 'inactive']);
        $noPin = $this->branch($tenant, 'Quezon City', null, null);
        $paracetamol = $this->product($tenant, $makati, 'Paracetamol 500mg');
        foreach ([$cebu, $taguig, $closed, $noPin] as $branch) {
            $this->stock($branch, $paracetamol, 10);
        }
        $this->stock($makati, $paracetamol, 0);
        $rivalBranch = $this->branch(Tenant::create(['name' => 'Rival', 'slug' => 'rival', 'status' => 'active']), 'Rival Makati', 14.5547, 121.0244);
        $this->stock($rivalBranch, $paracetamol, 10);

        // Standing in Makati: Taguig is a few km away, Cebu hundreds.
        $branches = $this->getJson(route('api.v1.products.availability', [$paracetamol, 'lat' => 14.5547, 'lng' => 121.0244]))
            ->assertOk()
            ->json('data');

        $this->assertSame([$taguig->id, $cebu->id], array_column($branches, 'id'));
        $this->assertLessThan(10, $branches[0]['distance_km']);
        $this->assertGreaterThan(500, $branches[1]['distance_km']);
        $this->assertSame(10, $branches[0]['quantity']);
    }

    public function test_availability_counts_the_requested_variant_only(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $syrup = $this->product($tenant, $makati, 'Ascorbic Acid Syrup');
        $small = ProductVariant::create(['product_id' => $syrup->id, 'size' => '60 ml', 'sku' => 'ASC-60', 'stock_quantity' => 1]);
        $large = ProductVariant::create(['product_id' => $syrup->id, 'size' => '120 ml', 'sku' => 'ASC-120', 'stock_quantity' => 1]);
        $this->stock($cebu, $syrup, 3, $small);

        $this->getJson(route('api.v1.products.availability', [$syrup, 'variant' => $small->id]))->assertOk()->assertJsonPath('data.0.id', $cebu->id);
        $this->getJson(route('api.v1.products.availability', [$syrup, 'variant' => $large->id]))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_bought_before_lists_the_buyers_own_medicines_the_branch_has_newest_first(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $paracetamol = $this->product($tenant, $makati, 'Paracetamol 500mg');
        $cetirizine = $this->product($tenant, $makati, 'Cetirizine 10mg');
        $loratadine = $this->product($tenant, $makati, 'Loratadine 10mg');
        $amoxicillin = $this->product($tenant, $makati, 'Amoxicillin 500mg');
        $this->stock($cebu, $paracetamol, 5);
        $this->stock($cebu, $cetirizine, 3);
        $this->stock($cebu, $amoxicillin, 4);
        $buyer = User::factory()->create();
        $this->bought($buyer, $cebu, $paracetamol, 'pending', now()->subDays(2));
        $this->bought($buyer, $cebu, $cetirizine, 'delivered', now()->subDay());
        $this->bought($buyer, $cebu, $loratadine, 'delivered', now());
        $this->bought($buyer, $cebu, $amoxicillin, 'cancelled', now());
        $this->bought(User::factory()->create(), $cebu, $amoxicillin, 'delivered', now());

        $this->actingAs($buyer)
            ->getJson(route('api.v1.products.bought-before', ['branch' => $cebu->id]))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$cetirizine->id, $paracetamol->id]);
    }

    public function test_bought_before_needs_a_signed_in_buyer(): void
    {
        [, , $cebu] = $this->pharmacy();

        $this->getJson(route('api.v1.products.bought-before', ['branch' => $cebu->id]))->assertUnauthorized();
    }

    public function test_best_sellers_rank_what_the_branch_sold_most_without_prescription_medicines(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $paracetamol = $this->product($tenant, $makati, 'Paracetamol 500mg');
        $cetirizine = $this->product($tenant, $makati, 'Cetirizine 10mg');
        $cancelled = $this->product($tenant, $makati, 'Loratadine 10mg');
        $prescription = $this->product($tenant, $makati, 'Amoxicillin 500mg', ['requires_prescription' => true]);
        $soldElsewhere = $this->product($tenant, $makati, 'Ascorbic Acid 500mg');
        $soldLongAgo = $this->product($tenant, $makati, 'Zinc 10mg');
        $soldOut = $this->product($tenant, $makati, 'Ibuprofen 200mg');
        foreach ([$paracetamol, $cetirizine, $cancelled, $prescription, $soldElsewhere, $soldLongAgo] as $product) {
            $this->stock($cebu, $product, 10);
        }
        $this->stock($cebu, $soldOut, 0);
        $buyer = User::factory()->create();
        $this->bought($buyer, $cebu, $paracetamol, 'delivered', now(), quantity: 3);
        $this->bought($buyer, $cebu, $cetirizine, 'pending', now(), quantity: 5);
        $this->bought($buyer, $cebu, $cancelled, 'cancelled', now(), quantity: 9);
        $this->bought($buyer, $cebu, $prescription, 'delivered', now(), quantity: 9);
        $this->bought($buyer, $makati, $soldElsewhere, 'delivered', now(), quantity: 9);
        $this->bought($buyer, $cebu, $soldLongAgo, 'delivered', now()->subDays(40), quantity: 9);
        $this->bought($buyer, $cebu, $soldOut, 'delivered', now(), quantity: 9);

        $this->getJson(route('api.v1.products.best-sellers', ['branch' => $cebu->id]))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$cetirizine->id, $paracetamol->id]);
    }

    private function bought(User $buyer, Store $branch, Product $product, string $status, CarbonInterface $at, int $quantity = 1): void
    {
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'JM-'.fake()->unique()->numerify('######'),
            'status' => 'pending',
            'recipient_name' => 'Ana Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Mabini Street',
            'city' => $branch->city,
            'subtotal' => '10.00',
            'shipping_amount' => '0.00',
            'total' => '10.00',
        ]);
        $order->sellerOrders()->create([
            'tenant_id' => $branch->tenant_id,
            'store_id' => $branch->id,
            'status' => $status,
            'subtotal' => '10.00',
            'shipping_amount' => '0.00',
            'total' => '10.00',
        ])->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => '10.00',
            'quantity' => $quantity,
            'line_total' => '10.00',
        ])->forceFill(['created_at' => $at])->save();
    }

    /** @return array{0: Tenant, 1: Store, 2: Store} a pharmacy with branches in Makati and Cebu */
    private function pharmacy(): array
    {
        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active']);

        return [$tenant, $this->branch($tenant, 'Makati', 14.5547, 121.0244), $this->branch($tenant, 'Cebu', 10.3157, 123.8854)];
    }

    private function branch(Tenant $tenant, string $city, ?float $latitude, ?float $longitude): Store
    {
        return Store::create([
            'tenant_id' => $tenant->id,
            'name' => "{$tenant->name} {$city}",
            'slug' => str("{$tenant->name} {$city}")->slug(),
            'phone' => '+639171234567',
            'address_line' => '10 Rizal Street',
            'city' => $city,
            'status' => 'active',
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function product(Tenant $tenant, Store $store, string $name, array $attributes = []): Product
    {
        return Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'sku' => strtoupper(str($name)->slug()),
            'price' => '25.00',
            'stock_quantity' => 0,
            'status' => 'active',
            ...$attributes,
        ]);
    }

    private function stock(Store $branch, Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        BranchStock::create([
            'store_id' => $branch->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'variant_key' => $variant?->id ?? 'default',
            'quantity' => $quantity,
        ]);
    }
}
