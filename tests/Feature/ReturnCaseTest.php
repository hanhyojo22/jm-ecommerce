<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\SellerEarning;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_quantity_window_and_tenant_boundaries(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$buyer, $seller, $order, $item] = $this->deliveredOrder(2, now()->subDays(7));
        $otherBuyer = User::factory()->create(['email_verified_at' => now()]);
        $data = $this->requestData($item->id, 1);

        $this->actingAs($otherBuyer)->post(route('returns.store', $order), $data)->assertNotFound();
        $this->actingAs($buyer)->post(route('returns.store', $order), $data)->assertRedirect();
        $case = ReturnCase::query()->sole();
        $this->actingAs($buyer)->get(route('returns.show', $case))->assertOk()->assertSee('Case history');
        $this->actingAs($buyer)->get(route('orders.show', $order->order))->assertOk()->assertSee('Returns and replacements');
        $this->actingAs($seller)->get(route('seller.returns.index'))->assertOk()->assertSee('1 need seller action');
        $this->actingAs($seller)->get(route('seller.returns.show', $case))->assertOk()->assertSee('Send offer');
        $this->actingAs($otherBuyer)->get(route('returns.show', $case))->assertNotFound();
        $this->actingAs($buyer)->post(route('returns.store', $order), $this->requestData($item->id, 2))->assertSessionHasErrors('items');
        $this->actingAs($buyer)->post(route('returns.store', $order), array_merge($this->requestData($item->id, 1), ['items' => [['order_item_id' => $item->id, 'quantity' => 1], ['order_item_id' => $item->id, 'quantity' => 1]]]))->assertSessionHasErrors('items');
        $this->actingAs($buyer)->post(route('returns.store', $order), $this->requestData($item->id, 1))->assertRedirect();
        $this->assertDatabaseCount('return_cases', 2);
        $this->actingAs($buyer)->post(route('returns.store', $order), $data)->assertSessionHasErrors('items');

        [$lateBuyer, , $lateOrder, $lateItem] = $this->deliveredOrder(1, now()->subDays(7)->subSecond());
        $this->actingAs($lateBuyer)->post(route('returns.store', $lateOrder), $this->requestData($lateItem->id, 1))->assertSessionHasErrors('items');
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $this->actingAs($admin)->post(route('admin.returns.store', $lateOrder), $this->requestData($lateItem->id, 1))->assertRedirect();
        $this->actingAs($admin)->get(route('admin.returns.show', ReturnCase::query()->where('seller_order_id', $lateOrder->id)->sole()))->assertOk();
        $this->actingAs($seller)->get(route('seller.returns.show', ReturnCase::query()->where('seller_order_id', $lateOrder->id)->sole()))->assertNotFound();
    }

    public function test_seller_refund_flow_preserves_order_total_and_private_proof(): void
    {
        Storage::fake('chat_attachments');
        [$buyer, $seller, $sellerOrder, $item] = $this->deliveredOrder(2);
        $this->actingAs($buyer)->post(route('returns.store', $sellerOrder), $this->requestData($item->id, 2))->assertRedirect();
        $case = ReturnCase::query()->sole();
        $otherSeller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $this->actingAs($otherSeller)->get(route('seller.returns.show', $case))->assertForbidden();
        $this->actingAs($otherSeller)->patch(route('seller.returns.update', $case), ['action' => 'reject', 'note' => 'I cannot approve this.'])->assertForbidden();

        $this->actingAs($seller)->patch(route('seller.returns.update', $case), [
            'action' => 'offer', 'remedy' => 'refund', 'note' => 'We can refund both damaged units.',
            'return_instructions' => 'Pack the two units for pickup.', 'return_method' => 'Seller-paid pickup',
        ])->assertSessionHas('status');
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'accept'])->assertSessionHas('status');
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'handoff', 'proof' => UploadedFile::fake()->image('handoff.jpg')])->assertSessionHas('status');
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'receive'])->assertSessionHas('status');
        $caseItem = $case->items()->sole();
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'restock', 'case_item_id' => $caseItem->id, 'quantity' => 1])->assertSessionHas('status');
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'restock', 'case_item_id' => $caseItem->id, 'quantity' => 2])->assertSessionHasErrors('quantity');
        $this->assertSame(6, Product::findOrFail($item->product_id)->stock_quantity);
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'refund', 'paid_at' => now()->toDateString(), 'proof' => UploadedFile::fake()->image('refund.jpg')])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('return_case_outcomes', 0);
        $this->actingAs($seller)->patch(route('seller.orders.cod-collection', $sellerOrder))->assertSessionHas('status');
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'refund', 'paid_at' => now()->toDateString(), 'proof' => UploadedFile::fake()->image('refund.jpg')])->assertSessionHas('status');
        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'awaiting_confirmation']);
        $this->assertDatabaseHas('return_case_outcomes', ['return_case_id' => $case->id, 'kind' => 'refund', 'amount' => '500.00']);
        $this->assertDatabaseHas('orders', ['id' => $sellerOrder->order_id, 'total' => '500.00']);
        $this->assertDatabaseCount('seller_earnings', 0);
        $evidence = $case->evidence()->where('kind', 'refund')->sole();
        Storage::disk('chat_attachments')->assertExists($evidence->path);
        $this->actingAs($otherSeller)->get(route('returns.evidence', [$case, $evidence]))->assertNotFound();
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get(route('returns.evidence', [$case, $evidence]))->assertNotFound();
        $this->actingAs($buyer)->get(route('returns.evidence', [$case, $evidence]))->assertOk();
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'verify', 'note' => 'The seller supplied a payment receipt.'])->assertSessionHasErrors('action');
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'confirm'])->assertSessionHas('status');
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'confirm'])->assertSessionHasErrors('action');
        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'completed']);
    }

    public function test_appeal_admin_decision_and_replacement_reduce_stock_once(): void
    {
        [$buyer, $seller, $order, $item, $product] = $this->deliveredOrder();
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('returns.store', $order), $this->requestData($item->id, 1))->assertRedirect();
        $case = ReturnCase::query()->sole();
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'reject', 'note' => 'I do not think the item is defective.'])->assertSessionHas('status');
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'appeal', 'note' => 'The defect is visible in my photos.'])->assertSessionHas('status');
        $this->actingAs($seller)->patch(route('admin.returns.update', $case), ['action' => 'decide', 'decision' => 'replacement', 'note' => 'Replacement is appropriate.', 'return_waived' => 1])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'decide', 'decision' => 'replacement', 'note' => 'Replacement is appropriate.', 'return_waived' => 1])->assertSessionHas('status');
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'ship', 'reference' => 'TRACK-1'])->assertSessionHas('status');
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'ship', 'reference' => 'TRACK-2'])->assertSessionHasErrors('action');
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'appeal', 'note' => 'The replacement has not arrived.'])->assertSessionHas('status');
        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'appealed']);
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'verify', 'note' => 'Carrier proof and buyer confirmation reviewed.'])->assertSessionHas('status');
        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'completed']);
    }

    public function test_legacy_issue_is_preserved_and_new_cod_is_collected_per_seller_order(): void
    {
        [$buyer, $seller, $first] = $this->deliveredOrder();
        [, $secondSeller, $second] = $this->deliveredOrder();
        $second->order()->associate($first->order);
        $second->save();
        $issue = OrderIssue::query()->create(['seller_order_id' => $first->id, 'buyer_id' => $buyer->id, 'type' => 'return_refund', 'details' => 'Historical support request.']);
        $this->actingAs($seller)->patch(route('seller.orders.cod-collection', $first))->assertSessionHas('status');
        $this->actingAs($seller)->patch(route('seller.orders.cod-collection', $first))->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('payments', ['order_id' => $first->order_id, 'status' => 'pending']);
        $this->assertNotNull($first->fresh()->cod_collected_at);
        $this->assertNull($second->fresh()->cod_collected_at);
        $this->actingAs($secondSeller)->patch(route('seller.orders.cod-collection', $first))->assertNotFound();
        $this->actingAs($secondSeller)->patch(route('seller.orders.cod-collection', $second))->assertSessionHas('status');
        $this->assertDatabaseHas('payments', ['order_id' => $first->order_id, 'status' => 'paid']);
        $this->assertDatabaseCount('seller_earnings', 0);
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $item = $first->items()->sole();
        $this->actingAs($admin)->post(route('admin.returns.store', $first), $this->requestData($item->id, 1) + ['order_issue_id' => $issue->id])->assertRedirect();
        $this->assertDatabaseHas('return_cases', ['seller_order_id' => $first->id, 'order_issue_id' => $issue->id]);
        $this->assertDatabaseHas('order_issues', ['id' => $issue->id, 'details' => 'Historical support request.', 'status' => 'resolved']);
    }

    public function test_migration_backfills_delivery_and_voids_only_unpaid_cod_earnings(): void
    {
        [, , $available] = $this->deliveredOrder();
        [, , $paidOut] = $this->deliveredOrder();
        $available->order()->firstOrFail()->payment()->update(['status' => 'paid']);
        $paidOut->order()->firstOrFail()->payment()->update(['status' => 'paid']);
        foreach ([$available, $paidOut] as $order) {
            SellerEarning::create([
                'seller_order_id' => $order->id, 'tenant_id' => $order->tenant_id, 'store_id' => $order->store_id,
                'gross_amount' => $order->total, 'commission_amount' => '0.00', 'net_amount' => $order->total,
                'status' => $order->id === $available->id ? 'available' : 'paid_out', 'available_at' => now(),
            ]);
        }
        $migration = require database_path('migrations/2026_10_18_141808_create_return_cases_and_cod_tracking.php');
        $migration->down();
        $migration->up();

        $this->assertNotNull($available->fresh()->delivered_at);
        $this->assertNotNull($available->fresh()->cod_collected_at);
        $this->assertNotNull($available->earning()->sole()->voided_at);
        $this->assertNull($paidOut->earning()->sole()->voided_at);
        $this->assertSame('paid_out', $paidOut->earning()->sole()->status);
    }

    public function test_admin_cod_correction_is_scoped_to_one_seller_order(): void
    {
        [, $seller, $first] = $this->deliveredOrder();
        [, , $second] = $this->deliveredOrder();
        $second->order()->associate($first->order()->firstOrFail());
        $second->save();
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.orders.seller-cod-collection', $first))->assertSessionHas('status');
        $firstCollectedAt = $first->fresh()->cod_collected_at;
        $this->assertNull($second->fresh()->cod_collected_at);
        $this->assertDatabaseHas('payments', ['order_id' => $first->order_id, 'status' => 'pending']);
        $this->actingAs($seller)->patch(route('admin.orders.seller-cod-collection', $second))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.orders.seller-cod-collection', $second))->assertSessionHas('status');
        $this->assertSame($firstCollectedAt->toDateTimeString(), $first->fresh()->cod_collected_at->toDateTimeString());
        $this->assertDatabaseHas('payments', ['order_id' => $first->order_id, 'status' => 'paid']);
    }

    public function test_support_closes_a_return_the_buyer_abandons(): void
    {
        [$buyer, $seller, $sellerOrder, $item] = $this->deliveredOrder();
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('returns.store', $sellerOrder), $this->requestData($item->id, 1));
        $case = ReturnCase::query()->sole();
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), [
            'action' => 'offer', 'remedy' => 'refund', 'note' => 'Send the unit back for a refund.',
            'return_instructions' => 'Pack the unit for pickup.', 'return_method' => 'Seller-paid pickup',
        ]);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'accept']);

        $this->assertSame('awaiting_return', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->return_by);

        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'close', 'note' => 'The buyer never sent the unit back.'])->assertSessionHasErrors('action');

        $this->travel(8)->days();
        $this->actingAs($admin)->get(route('admin.returns.index', ['needs_action' => 1]))->assertSee('1 need support review');
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'close', 'note' => 'The buyer never sent the unit back.'])->assertSessionHas('status');

        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'closed']);
        $this->assertNotNull($case->fresh()->completed_at);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'handoff', 'proof' => UploadedFile::fake()->image('late.jpg')])->assertSessionHasErrors('action');
    }

    public function test_support_takes_over_a_return_leg_the_seller_leaves_open(): void
    {
        Storage::fake('chat_attachments');
        [$buyer, $seller, $sellerOrder, $item] = $this->deliveredOrder();
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('returns.store', $sellerOrder), $this->requestData($item->id, 1));
        $case = ReturnCase::query()->sole();
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), [
            'action' => 'offer', 'remedy' => 'refund', 'note' => 'Send the unit back for a refund.',
            'return_instructions' => 'Pack the unit for pickup.', 'return_method' => 'Seller-paid pickup',
        ]);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'accept']);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'handoff', 'proof' => UploadedFile::fake()->image('handoff.jpg')]);

        $this->assertSame('awaiting_receipt', $case->fresh()->status);
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'takeover'])->assertSessionHasErrors('action');

        $this->travel(21)->days();
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'takeover'])->assertSessionHas('status');
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), [
            'action' => 'decide', 'decision' => 'refund', 'note' => 'The seller never recorded the parcel.',
            'return_instructions' => 'Already in transit.', 'return_method' => 'Seller-paid pickup',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'awaiting_receipt']);
    }

    public function test_a_remedy_decided_after_receipt_does_not_ask_for_the_goods_again(): void
    {
        Storage::fake('chat_attachments');
        [$buyer, $seller, $sellerOrder, $item] = $this->deliveredOrder();
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('returns.store', $sellerOrder), $this->requestData($item->id, 1));
        $case = ReturnCase::query()->sole();
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), [
            'action' => 'offer', 'remedy' => 'refund', 'note' => 'Send the unit back for a refund.',
            'return_instructions' => 'Pack the unit for pickup.', 'return_method' => 'Seller-paid pickup',
        ]);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'accept']);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'handoff', 'proof' => UploadedFile::fake()->image('handoff.jpg')]);
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'receive']);

        $this->travel(14)->days();
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'takeover'])->assertSessionHas('status');
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), [
            'action' => 'decide', 'decision' => 'replacement', 'note' => 'A replacement is the fair outcome here.',
            'return_instructions' => 'The seller already holds the unit.', 'return_method' => 'Seller-paid pickup',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'awaiting_fulfillment', 'remedy' => 'replacement']);
    }

    public function test_support_sets_an_unsupported_outcome_aside_and_returns_its_stock(): void
    {
        [$buyer, $seller, $sellerOrder, $item, $product] = $this->deliveredOrder();
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('returns.store', $sellerOrder), $this->requestData($item->id, 1));
        $case = ReturnCase::query()->sole();
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), [
            'action' => 'offer', 'remedy' => 'replacement', 'note' => 'We will ship a replacement unit.', 'return_waived' => 1,
        ]);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'accept']);
        $this->actingAs($seller)->patch(route('seller.returns.update', $case), ['action' => 'ship', 'reference' => 'TRACK-1']);
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'appeal', 'note' => 'That tracking number was never scanned.']);
        $this->assertSame(4, $product->fresh()->stock_quantity);

        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'decide', 'decision' => 'refund', 'note' => 'Switching the remedy to a refund.', 'return_waived' => 1])->assertSessionHasErrors('action');
        $this->actingAs($admin)->patch(route('admin.returns.update', $case), ['action' => 'reopen', 'note' => 'The tracking reference does not resolve to a shipment.'])->assertSessionHas('status');

        $this->assertDatabaseCount('return_case_outcomes', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'awaiting_fulfillment']);
    }

    public function test_the_reminder_command_nudges_overdue_cases_once(): void
    {
        [$buyer, , $sellerOrder, $item] = $this->deliveredOrder();
        [$otherBuyer, , $otherSellerOrder, $otherItem] = $this->deliveredOrder();
        $this->actingAs($buyer)->post(route('returns.store', $sellerOrder), $this->requestData($item->id, 1));
        $overdue = ReturnCase::query()->sole();
        $this->travel(49)->hours();
        $this->actingAs($otherBuyer)->post(route('returns.store', $otherSellerOrder), $this->requestData($otherItem->id, 1));
        $onTime = ReturnCase::query()->whereKeyNot($overdue->id)->sole();

        $this->artisan('returns:remind')->assertSuccessful();
        $this->artisan('returns:remind')->assertSuccessful();

        $this->assertSame(1, $overdue->events()->where('type', 'reminder')->count());
        $this->assertSame(0, $onTime->events()->where('type', 'reminder')->count());
    }

    public function test_a_buyer_escalates_a_seller_who_misses_the_response_window(): void
    {
        [$buyer, , $sellerOrder, $item] = $this->deliveredOrder();
        $this->actingAs($buyer)->post(route('returns.store', $sellerOrder), $this->requestData($item->id, 1));
        $case = ReturnCase::query()->sole();

        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'appeal', 'note' => 'The seller has not replied at all.'])->assertSessionHasErrors('action');

        $this->travel(49)->hours();
        $this->actingAs($buyer)->patch(route('returns.update', $case), ['action' => 'appeal', 'note' => 'The seller has not replied at all.'])->assertSessionHas('status');

        $this->assertDatabaseHas('return_cases', ['id' => $case->id, 'status' => 'appealed']);
    }

    private function requestData(string $itemId, int $quantity): array
    {
        return ['reason' => 'damaged', 'requested_remedy' => 'refund', 'details' => 'The product arrived badly damaged.', 'items' => [['order_item_id' => $itemId, 'quantity' => $quantity]]];
    }

    private function deliveredOrder(int $quantity = 1, ?Carbon $deliveredAt = null): array
    {
        $suffix = strtolower((string) str()->random(8));
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Seller '.$suffix, 'slug' => 'seller-'.$suffix, 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Store '.$suffix, 'slug' => 'store-'.$suffix, 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);
        $product = Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => 'tote-'.$suffix, 'sku' => 'SKU-'.$suffix, 'price' => '250.00', 'stock_quantity' => 5, 'status' => 'active']);
        $total = bcmul('250.00', (string) $quantity, 2);
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'RET-'.$suffix, 'status' => 'delivered', 'recipient_name' => 'Buyer', 'phone' => '+639171234567', 'address_line' => 'Buyer Street', 'city' => 'Manila', 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => $total]);
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => 'delivered', 'delivered_at' => $deliveredAt ?? now(), 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total]);
        $item = $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => '250.00', 'quantity' => $quantity, 'line_total' => $total]);

        return [$buyer, $seller, $sellerOrder, $item, $product];
    }
}
