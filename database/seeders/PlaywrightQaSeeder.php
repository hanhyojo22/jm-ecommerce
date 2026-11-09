<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PlaywrightQaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Playwright fixtures may only be seeded in the testing environment.');
        }

        $this->call(CategorySeeder::class);

        User::factory()->create(['name' => 'QA Integration Customer', 'email' => 'qa-integration@example.test', 'clerk_user_id' => 'qa-integration-identity']);

        $customer = User::factory()->create([
            'name' => 'QA Customer',
            'email' => 'qa-customer@example.test',
            'clerk_user_id' => 'qa-customer-identity',
        ]);
        User::factory()->create([
            'name' => 'QA Applicant',
            'email' => 'qa-applicant@example.test',
            'clerk_user_id' => 'qa-applicant-identity',
        ]);
        User::factory()->create([
            'name' => 'QA Responsive Applicant',
            'email' => 'qa-responsive-applicant@example.test',
            'clerk_user_id' => 'qa-responsive-applicant-identity',
        ]);
        $rejectedApplicant = User::factory()->create([
            'name' => 'QA Rejected Applicant',
            'email' => 'qa-rejected@example.test',
            'clerk_user_id' => 'qa-rejected-identity',
        ]);
        $rejectedApplicant->sellerApplications()->create([
            'store_name' => 'QA Rejected Shop',
            'status' => 'rejected',
            'review_note' => 'Verification documents did not match the applicant.',
        ]);
        $seller = User::factory()->seller()->create([
            'name' => 'QA Seller',
            'email' => 'qa-seller@example.test',
            'clerk_user_id' => 'qa-seller-identity',
        ]);
        $otherSeller = User::factory()->seller()->create([
            'name' => 'QA Other Seller',
            'email' => 'qa-other-seller@example.test',
            'clerk_user_id' => 'qa-other-seller-identity',
        ]);
        User::factory()->administrator()->create([
            'name' => 'QA Admin',
            'email' => 'qa-admin@example.test',
            'clerk_user_id' => 'qa-admin-identity',
        ]);

        $category = Category::query()->where('slug', 'electronics')->firstOrFail();
        [$tenant, $store] = $this->storeFor($seller, 'qa-northwind', 'QA Northwind');
        [$otherTenant, $otherStore] = $this->storeFor($otherSeller, 'qa-southwind', 'QA Southwind');

        $product = $this->product($tenant, $store, $category, 'QA Canvas Tote', 'qa-canvas-tote', 'QA-TOTE-001', '19.99', 25);
        $chatProduct = $this->product($tenant, $store, $category, 'QA Chat Sample', 'qa-chat-sample', 'QA-CHAT-001', '24.99', 10);
        $this->product($otherTenant, $otherStore, $category, 'Other Seller QA Product', 'other-qa-product', 'QA-OTHER-001', '39.99', 10);
        $historyProduct = $this->product($otherTenant, $otherStore, $category, 'QA History Sample', 'qa-history-sample', 'QA-HISTORY-001', '29.99', 10);
        $variantProduct = $this->product($otherTenant, $otherStore, $category, 'QA Variant Speaker', 'qa-variant-speaker', 'QA-SPEAKER', '10.00', 5);
        foreach (['black' => 'home-product-collection.jpg', 'white' => 'categories/electronics.png'] as $color => $asset) {
            Storage::disk('product_images')->put('qa/variant-'.$color.'.jpg', file_get_contents(public_path('images/'.$asset)));
        }
        foreach ([['Black', '12.35', 2], ['White', '13.50', 0], ['Navy', null, 3]] as [$color, $price, $stock]) {
            $variant = $variantProduct->variants()->create(['color' => $color, 'size' => 'Standard', 'sku' => 'QA-SPEAKER-'.strtoupper($color), 'price' => $price, 'stock_quantity' => $stock]);
            $variantProduct->images()->create(['color' => $color, 'path' => 'qa/variant-'.($color === 'White' ? 'white' : 'black').'.jpg', 'position' => $variantProduct->images()->count()]);
        }
        $variantProduct->images()->create(['color' => 'Black', 'path' => 'qa/variant-white.jpg', 'position' => 3]);
        $draft = $this->product($tenant, $store, $category, 'QA Approval Draft', 'qa-approval-draft', 'QA-APPROVAL', '15.00', 3);
        $draft->update(['status' => 'draft']);
        $draft->images()->create(['path' => 'qa/variant-black.jpg', 'position' => 0]);
        for ($number = 1; $number <= 21; $number++) {
            $arrival = $this->product($otherTenant, $otherStore, $category, "QA Arrival {$number}", "qa-arrival-{$number}", "QA-ARRIVAL-{$number}", '29.99', 10);
            $arrival->created_at = now()->subDay()->subMinutes($number);
            $arrival->save();
        }

        $historyConversation = Conversation::query()->create(['buyer_id' => $customer->id, 'store_id' => $otherStore->id]);
        for ($number = 1; $number <= 225; $number++) {
            $historyConversation->messages()->create([
                'sender_id' => $customer->id,
                'body' => "History message {$number}",
                ...($number === 225 ? [
                    'product_id' => $historyProduct->id,
                    'context_snapshot' => [
                        'type' => 'product',
                        'title' => $historyProduct->name,
                        'price' => $historyProduct->price,
                        'image_path' => $historyProduct->thumbnail?->path,
                        'slug' => $historyProduct->slug,
                        'store_slug' => $otherStore->slug,
                    ],
                ] : []),
            ]);
        }
        $historyConversation->update(['latest_message_at' => now()]);

        $order = Order::query()->create(['user_id' => $customer->id, 'number' => 'QA-REVIEW-001', 'status' => 'delivered', 'recipient_name' => $customer->name, 'phone' => '+639171234567', 'address_line' => '10 QA Street', 'city' => 'Manila', 'subtotal' => '19.99', 'shipping_amount' => '0', 'total' => '19.99']);
        $order->payment()->create(['method' => 'cod', 'status' => 'paid', 'amount' => '19.99']);
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => 'delivered', 'delivered_at' => now(), 'cod_collected_at' => now(), 'subtotal' => '19.99', 'shipping_amount' => '0', 'total' => '19.99']);
        $item = $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => '19.99', 'quantity' => 1, 'line_total' => '19.99']);
        $item->review()->create(['product_id' => $product->id, 'user_id' => $customer->id, 'rating' => 5, 'body' => 'Great tote.', 'status' => 'published']);

        $rowOrder = Order::query()->create(['user_id' => $customer->id, 'number' => 'QA-ROWS-001', 'status' => 'delivered', 'recipient_name' => $customer->name, 'phone' => '+639171234567', 'address_line' => '10 QA Street', 'city' => 'Manila', 'subtotal' => '80.97', 'shipping_amount' => '0', 'total' => '80.97']);
        $rowOrder->payment()->create(['method' => 'cod', 'status' => 'paid', 'amount' => '80.97']);
        $rowSellerOrder = $rowOrder->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => 'delivered', 'delivered_at' => now(), 'cod_collected_at' => now(), 'subtotal' => '80.97', 'shipping_amount' => '0', 'total' => '80.97']);
        $rowSellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => '19.99', 'quantity' => 1, 'line_total' => '19.99']);
        $rowSellerOrder->items()->create(['product_id' => $chatProduct->id, 'product_name' => $chatProduct->name, 'sku' => $chatProduct->sku, 'variant_label' => 'Blue / Medium', 'unit_price' => '24.99', 'quantity' => 2, 'line_total' => '49.98']);
        $rowSellerOrder->items()->create(['product_name' => 'QA Archived Accessory', 'sku' => 'QA-ARCHIVE-001', 'unit_price' => '5.00', 'quantity' => 1, 'line_total' => '5.00']);
        $rowSellerOrder->items()->create(['product_name' => 'QA Retired Pouch', 'sku' => 'QA-RETIRED-001', 'unit_price' => '6.00', 'quantity' => 1, 'line_total' => '6.00']);
    }

    /**
     * @return array{0: Tenant, 1: Store}
     */
    private function storeFor(User $seller, string $slug, string $name): array
    {
        $tenant = Tenant::query()->create(['name' => $name, 'slug' => $slug, 'status' => 'active']);
        $store = Store::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name.' Store',
            'slug' => $slug.'-store',
            'phone' => '+639171234567',
            'address_line' => '10 QA Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);

        TenantMember::query()->create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$tenant, $store];
    }

    private function product(Tenant $tenant, Store $store, Category $category, string $name, string $slug, string $sku, string $price, int $stock): Product
    {
        return Product::query()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'description' => 'Deterministic Playwright fixture for marketplace QA.',
            'price' => $price,
            'stock_quantity' => $stock,
            'status' => 'active',
        ]);
    }
}
