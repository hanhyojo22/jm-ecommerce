<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tenant;
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
