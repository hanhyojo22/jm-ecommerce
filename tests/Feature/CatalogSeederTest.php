<?php

namespace Tests\Feature;

use App\Models\Store;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_three_stores_with_twenty_imaged_products_each_without_duplicates(): void
    {
        Storage::fake('product_images');

        $this->seed(CatalogSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->assertDatabaseCount('stores', 3);
        $this->assertDatabaseCount('products', 60);
        $this->assertDatabaseCount('product_images', 60);
        $this->assertDatabaseCount('tenant_members', 3);

        foreach (['northstar-tech', 'hearth-and-home', 'trail-and-tide'] as $slug) {
            $store = Store::query()->where('slug', $slug)->firstOrFail();

            $this->assertSame(20, $store->products()->count());
            $this->assertSame('active', $store->status);
        }

        Storage::disk('product_images')->assertExists('products/catalog/northstar-tech/wireless-over-ear-headphones.webp');
    }
}
