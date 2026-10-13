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
use RuntimeException;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        foreach ($this->stores() as $slug => $sample) {
            $seller = User::query()->firstOrCreate(
                ['email' => $slug.'@example.test'],
                [
                    'name' => $sample['name'].' Seller',
                    'password' => Hash::make(Str::random(32)),
                    'account_type' => 'seller',
                    'is_active' => true,
                    'preferred_workspace' => 'seller',
                ]
            );

            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $sample['name'], 'status' => 'active']
            );

            $store = Store::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $sample['name'],
                    'description' => $sample['description'],
                    'phone' => '+639171234567',
                    'address_line' => '123 Market Street',
                    'city' => 'Manila',
                    'status' => 'active',
                ]
            );

            TenantMember::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $seller->id],
                ['role' => 'owner']
            );

            $category = Category::query()->where('slug', $sample['category'])->firstOrFail();

            foreach ($sample['products'] as $index => $name) {
                $productSlug = Str::slug($name);
                $product = Product::query()->firstOrCreate(
                    ['store_id' => $store->id, 'slug' => $productSlug],
                    [
                        'tenant_id' => $tenant->id,
                        'category_id' => $category->id,
                        'name' => $name,
                        'sku' => strtoupper(str_replace('-', '', $slug)).'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                        'description' => $name.' from '.$sample['name'].'. A sample product for the JMMart marketplace.',
                        'price' => ($sample['base_price'] + $sample['price_step'] * $index).'.00',
                        'brand' => $sample['name'],
                        'condition' => 'new',
                        'free_shipping' => true,
                        'stock_quantity' => 20 + $index,
                        'status' => 'active',
                    ]
                );

                if ($product->images()->exists()) {
                    continue;
                }

                $source = __DIR__.'/images/'.$slug.'/'.$productSlug.'.webp';
                $path = 'products/catalog/'.$slug.'/'.$productSlug.'.webp';

                if (! is_file($source)) {
                    throw new RuntimeException("Missing catalog image: {$source}");
                }

                $disk = Storage::disk('product_images');

                if ($disk instanceof AwsS3V3Adapter) {
                    $disk->getClient()->putObject([
                        'Bucket' => $disk->getConfig()['bucket'],
                        'Key' => $path,
                        'SourceFile' => $source,
                        'ContentType' => 'image/webp',
                    ]);
                } else {
                    $disk->put($path, file_get_contents($source));
                }

                $product->images()->create(['path' => $path, 'position' => 0]);
            }
        }
    }

    /**
     * @return array<string, array{name: string, description: string, category: string, base_price: int, price_step: int, products: list<string>}>
     */
    private function stores(): array
    {
        return [
            'northstar-tech' => [
                'name' => 'Northstar Tech',
                'description' => 'Useful electronics and everyday desk accessories.',
                'category' => 'electronics',
                'base_price' => 499,
                'price_step' => 150,
                'products' => [
                    'Wireless Over-Ear Headphones',
                    'Compact Bluetooth Speaker',
                    'Mechanical Keyboard',
                    'Ergonomic Wireless Mouse',
                    'USB-C Charging Hub',
                    'Adjustable Laptop Stand',
                    'Noise-Canceling Earbuds',
                    'Portable Power Bank',
                    'Smart Desk Lamp',
                    '1080p Webcam',
                    'Foldable Tablet Stand',
                    'Braided USB-C Cable',
                    'Desktop Microphone',
                    'Wireless Charging Pad',
                    'Mini Projector',
                    'Digital Alarm Clock',
                    'Portable SSD Case',
                    'LED Monitor Light Bar',
                    'Laptop Sleeve',
                    'Travel Tech Organizer',
                ],
            ],
            'hearth-and-home' => [
                'name' => 'Hearth & Home',
                'description' => 'Warm, practical pieces for kitchens and living spaces.',
                'category' => 'home-living',
                'base_price' => 199,
                'price_step' => 75,
                'products' => [
                    'Ceramic Pour-Over Dripper',
                    'Stoneware Dinner Plate',
                    'Linen Cushion Cover',
                    'Glass Storage Jar',
                    'Oak Cutting Board',
                    'Cotton Throw Blanket',
                    'Amber Glass Vase',
                    'Woven Laundry Basket',
                    'Ceramic Soap Dish',
                    'Stainless Tea Infuser',
                    'Ribbed Drinking Glass',
                    'Cotton Kitchen Towel',
                    'Wooden Serving Tray',
                    'Bamboo Drawer Organizer',
                    'Scented Soy Candle',
                    'Marble Coaster Set',
                    'Canvas Apron',
                    'Ceramic Plant Pot',
                    'Bedside Table Lamp',
                    'Waffle Bath Towel',
                ],
            ],
            'trail-and-tide' => [
                'name' => 'Trail & Tide',
                'description' => 'Simple gear for day trips, hikes, and weekends away.',
                'category' => 'sports-outdoors',
                'base_price' => 249,
                'price_step' => 110,
                'products' => [
                    'Insulated Water Bottle',
                    'Lightweight Daypack',
                    'Camping Lantern',
                    'Quick-Dry Hiking Shirt',
                    'Packable Rain Jacket',
                    'Trail Running Cap',
                    'Compact Picnic Blanket',
                    'Stainless Camp Mug',
                    'Trekking Poles',
                    'Waterproof Dry Bag',
                    'Portable First Aid Pouch',
                    'Hiking Socks',
                    'Foldable Camp Stool',
                    'Rechargeable Headlamp',
                    'Travel Packing Cubes',
                    'Outdoor Rope Set',
                    'Bike Handlebar Bag',
                    'Running Waist Pack',
                    'Microfiber Travel Towel',
                    'Camping Cook Pot',
                ],
            ],
        ];
    }
}
