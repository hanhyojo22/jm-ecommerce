<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use Database\Seeders\PharmacyImageSeeder;
use Database\Seeders\PharmacySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PharmacySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_open_branches_with_a_different_medicine_mix_and_can_run_twice(): void
    {
        Storage::fake('product_images');

        $this->seed(PharmacySeeder::class);

        $branches = Store::query()->get();
        $this->assertCount(4, $branches);
        $this->assertTrue($branches->every(fn (Store $branch): bool => $branch->status === 'active' && $branch->latitude !== null));
        $this->assertSame(20, Product::query()->count());
        $this->assertSame(6, Product::query()->where('requires_prescription', true)->count());
        foreach (Product::query()->with('images')->get() as $product) {
            $this->assertCount(1, $product->images);
            $path = 'products/catalog/pharmacy/'.$product->slug.'.webp';
            $this->assertSame($path, $product->images->sole()->path);
            Storage::disk('product_images')->assertExists($path);
        }

        $cefalexin = Product::query()->where('slug', 'cefalexin-500-mg-capsule')->sole();
        $this->assertSame(['JMMart Pharmacy Quezon City'], BranchStock::query()->where('product_id', $cefalexin->id)->with('store')->get()->pluck('store.name')->all());
        $this->assertSame(60, $cefalexin->stock_quantity);

        Storage::disk('product_images')->delete($cefalexin->images()->sole()->path);
        BranchStock::query()->where('product_id', $cefalexin->id)->update(['quantity' => 5]);
        $this->seed(PharmacySeeder::class);

        $this->assertSame(4, Store::query()->count());
        $this->assertSame(20, Product::query()->count());
        $this->assertDatabaseCount('product_images', 20);
        Storage::disk('product_images')->assertExists($cefalexin->images()->sole()->path);
        $this->assertSame(5, (int) BranchStock::query()->where('product_id', $cefalexin->id)->value('quantity'));
    }

    public function test_sample_images_preserve_uploaded_photos_and_do_not_match_other_tenants(): void
    {
        Storage::fake('product_images');
        $this->seed(PharmacySeeder::class);
        $medicine = Product::query()->where('slug', 'cefalexin-500-mg-capsule')->sole();
        $medicine->images()->delete();
        $photo = $medicine->images()->create(['path' => 'products/seller-upload.webp', 'position' => 0]);
        $otherTenant = Tenant::create(['name' => 'Other Pharmacy', 'slug' => 'other-pharmacy', 'status' => 'active']);
        $otherStore = Store::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Pharmacy', 'slug' => 'other-pharmacy', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $otherProduct = $otherStore->products()->create(['tenant_id' => $otherTenant->id, 'name' => $medicine->name, 'slug' => $medicine->slug, 'sku' => 'OTHER-RX', 'price' => '22.00', 'stock_quantity' => 1, 'status' => 'active', 'manufacturer' => 'Sample Laboratories']);

        $this->seed(PharmacyImageSeeder::class);

        $this->assertSame([$photo->id], $medicine->images()->pluck('id')->all());
        $this->assertFalse($otherProduct->images()->exists());
    }
}
