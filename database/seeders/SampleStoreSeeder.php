<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SampleStoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $seller = User::query()->firstOrCreate(
            ['email' => 'demo-seller@jmmart.test'],
            [
                'name' => 'Demo Seller',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'account_type' => 'seller',
                'platform_role' => null,
                'is_active' => true,
                'preferred_workspace' => 'seller',
            ]
        );

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'demo-store'],
            ['name' => 'Demo Store', 'status' => 'active']
        );

        $store = Store::query()->firstOrCreate(
            ['slug' => 'demo-store'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Demo Store',
                'description' => 'Sample JMMart seller store with affordable, quality finds.',
                'phone' => '+639171234567',
                'address_line' => '123 Sample Street',
                'city' => 'Manila',
                'status' => 'active',
            ]
        );

        if ($store->tenant_id !== $tenant->id) {
            $store->update(['tenant_id' => $tenant->id]);
        }

        TenantMember::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $seller->id],
            ['role' => 'owner']
        );

        foreach ($this->samples() as $index => $sample) {
            $category = Category::query()->where('slug', $sample['category'])->first();

            if ($category === null) {
                continue;
            }

            $product = Product::query()->firstOrCreate(
                ['sku' => $sample['sku']],
                [
                    'tenant_id' => $tenant->id,
                    'store_id' => $store->id,
                    'category_id' => $category->id,
                    'name' => $sample['name'],
                    'slug' => Str::slug($sample['name']).'-demo',
                    'description' => $sample['description'],
                    'price' => $sample['price'],
                    'compare_at_price' => $sample['compare_at_price'] ?? null,
                    'brand' => $sample['brand'],
                    'condition' => 'new',
                    'free_shipping' => false,
                    'stock_quantity' => $sample['stock'],
                    'status' => 'active',
                ]
            );

            if ($product->images()->exists()) {
                continue;
            }

            $path = 'products/demo-'.Str::slug($sample['name']).'.png';
            $this->storeImage($path, $this->placeholderImage($sample['name'], $sample['color'], $index));
            $product->images()->create(['path' => $path, 'position' => 0]);
        }
    }

    /**
     * @return array<int, array{name: string, category: string, sku: string, description: string, price: string, compare_at_price: ?string, brand: string, stock: int, color: string}>
     */
    private function samples(): array
    {
        return [
            ['name' => 'Aurora Wireless Headphones', 'category' => 'electronics', 'sku' => 'DEMO-HEADPHONES-001', 'description' => 'Sample over-ear wireless headphones with clear sound and long battery life.', 'price' => '2499.00', 'compare_at_price' => '3299.00', 'brand' => 'Aurora', 'stock' => 25, 'color' => '#0f766e'],
            ['name' => 'Vertex Mechanical Keyboard', 'category' => 'electronics', 'sku' => 'DEMO-KEYBOARD-002', 'description' => 'Sample tactile mechanical keyboard for work and play.', 'price' => '3450.00', 'compare_at_price' => null, 'brand' => 'Vertex', 'stock' => 18, 'color' => '#1d4ed8'],
            ['name' => 'Everyday Canvas Tote', 'category' => 'fashion', 'sku' => 'DEMO-TOTE-003', 'description' => 'Sample durable canvas tote for daily errands.', 'price' => '499.00', 'compare_at_price' => '699.00', 'brand' => 'Everyday', 'stock' => 60, 'color' => '#b45309'],
            ['name' => 'Classic Cotton Shirt', 'category' => 'fashion', 'sku' => 'DEMO-SHIRT-004', 'description' => 'Sample breathable cotton shirt with a clean fit.', 'price' => '899.00', 'compare_at_price' => null, 'brand' => 'Northwind', 'stock' => 40, 'color' => '#0e7490'],
            ['name' => 'Hydra Ceramic Mug 350ml', 'category' => 'home-living', 'sku' => 'DEMO-MUG-005', 'description' => 'Sample ceramic mug for coffee and tea.', 'price' => '349.00', 'compare_at_price' => '449.00', 'brand' => 'Hydra', 'stock' => 80, 'color' => '#9d174d'],
            ['name' => 'Lumen Desk Lamp', 'category' => 'home-living', 'sku' => 'DEMO-LAMP-006', 'description' => 'Sample warm LED desk lamp with adjustable brightness.', 'price' => '1299.00', 'compare_at_price' => null, 'brand' => 'Lumen', 'stock' => 22, 'color' => '#4d7c0f'],
            ['name' => 'Trail Running Shoes', 'category' => 'sports-outdoors', 'sku' => 'DEMO-SHOES-007', 'description' => 'Sample lightweight shoes for daily runs and trails.', 'price' => '2899.00', 'compare_at_price' => '3599.00', 'brand' => 'Trailhead', 'stock' => 30, 'color' => '#c2410c'],
            ['name' => 'Stainless Water Bottle 1L', 'category' => 'sports-outdoors', 'sku' => 'DEMO-BOTTLE-008', 'description' => 'Sample insulated stainless bottle that keeps drinks cold.', 'price' => '799.00', 'compare_at_price' => null, 'brand' => 'Hydra', 'stock' => 55, 'color' => '#0369a1'],
            ['name' => 'Natural Oat Soap Bar', 'category' => 'health-beauty', 'sku' => 'DEMO-SOAP-009', 'description' => 'Sample gentle oat soap bar for daily use.', 'price' => '149.00', 'compare_at_price' => '199.00', 'brand' => 'Pure', 'stock' => 120, 'color' => '#65a30d'],
            ['name' => 'Building Blocks Set 250pcs', 'category' => 'toys-collectibles', 'sku' => 'DEMO-BLOCKS-010', 'description' => 'Sample creative building blocks set for kids.', 'price' => '1199.00', 'compare_at_price' => null, 'brand' => 'BuildIt', 'stock' => 35, 'color' => '#dc2626'],
            ['name' => 'Leather Card Wallet', 'category' => 'bags-accessories', 'sku' => 'DEMO-WALLET-011', 'description' => 'Sample slim leather card wallet.', 'price' => '699.00', 'compare_at_price' => '899.00', 'brand' => 'Northwind', 'stock' => 45, 'color' => '#78350f'],
            ['name' => 'A5 Notebook Set of 3', 'category' => 'office-school', 'sku' => 'DEMO-NOTEBOOK-012', 'description' => 'Sample lined A5 notebooks for school and office.', 'price' => '299.00', 'compare_at_price' => null, 'brand' => 'Paperline', 'stock' => 90, 'color' => '#5b21b6'],
        ];
    }

    private function placeholderImage(string $name, string $hexColor, int $index): string
    {
        $size = 800;
        $image = imagecreatetruecolor($size, $size);

        if ($image === false) {
            throw new \RuntimeException('Could not create sample product image.');
        }

        [$red, $green, $blue] = $this->hexToRgb($hexColor);
        $background = imagecolorallocate($image, $red, $green, $blue);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 15, 23, 42);

        // Use white text on darker backgrounds, dark text on lighter ones.
        $brightness = ($red * 299 + $green * 587 + $blue * 114) / 1000;
        $textColor = $brightness > 150 ? $black : $white;

        imagefill($image, 0, 0, $background);

        $lines = explode("\n", wordwrap($name, 18, "\n"));
        $lines[] = 'JMMart Demo Store';
        $lines[] = 'Sample image '.($index + 1).' of 12';

        $font = 5;
        $lineHeight = imagefontheight($font) + 10;
        $totalHeight = count($lines) * $lineHeight;
        $y = (int) (($size - $totalHeight) / 2);

        foreach ($lines as $line) {
            $lineWidth = imagefontwidth($font) * strlen($line);
            $x = (int) (($size - $lineWidth) / 2);
            imagestring($image, $font, max($x, 10), $y, $line, $textColor);
            $y += $lineHeight;
        }

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    private function storeImage(string $path, string $contents): void
    {
        $disk = Storage::disk('product_images');

        if ($disk instanceof AwsS3V3Adapter) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'demo-product-');

            if ($temporaryPath === false) {
                throw new \RuntimeException('Could not create temporary sample image.');
            }

            file_put_contents($temporaryPath, $contents);

            try {
                $disk->getClient()->putObject([
                    'Bucket' => $disk->getConfig()['bucket'],
                    'Key' => $path,
                    'SourceFile' => $temporaryPath,
                    'ContentType' => 'image/png',
                ]);
            } finally {
                @unlink($temporaryPath);
            }

            return;
        }

        $disk->put($path, $contents);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
