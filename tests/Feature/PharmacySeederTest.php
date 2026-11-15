<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Store;
use Database\Seeders\PharmacySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_open_branches_with_a_different_medicine_mix_and_can_run_twice(): void
    {
        $this->seed(PharmacySeeder::class);

        $branches = Store::query()->get();
        $this->assertCount(4, $branches);
        $this->assertTrue($branches->every(fn (Store $branch): bool => $branch->status === 'active' && $branch->latitude !== null));
        $this->assertSame(20, Product::query()->count());
        $this->assertSame(6, Product::query()->where('requires_prescription', true)->count());

        $cefalexin = Product::query()->where('slug', 'cefalexin-500-mg-capsule')->sole();
        $this->assertSame(['JMMart Pharmacy Quezon City'], BranchStock::query()->where('product_id', $cefalexin->id)->with('store')->get()->pluck('store.name')->all());
        $this->assertSame(60, $cefalexin->stock_quantity);

        BranchStock::query()->where('product_id', $cefalexin->id)->update(['quantity' => 5]);
        $this->seed(PharmacySeeder::class);

        $this->assertSame(4, Store::query()->count());
        $this->assertSame(20, Product::query()->count());
        $this->assertSame(5, (int) BranchStock::query()->where('product_id', $cefalexin->id)->value('quantity'));
    }
}
