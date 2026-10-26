<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PlatformFeeStatement;
use App\Models\ReturnCase;
use App\Models\SellerEarning;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\GmailOrderMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivering_an_order_charges_one_percent_once_the_store_accepted_the_fee_terms(): void
    {
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $sellerOrder = $this->shippedSellerOrder($store, '150.50');

        $this->actingAs($owner)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'delivered'])->assertSessionHasNoErrors();

        $earning = SellerEarning::query()->where('seller_order_id', $sellerOrder->id)->sole();
        $this->assertSame('150.50', $earning->gross_amount);
        $this->assertSame('0.0100', $earning->commission_rate);
        $this->assertSame('1.51', $earning->commission_amount);
        $this->assertSame('148.99', $earning->net_amount);
        $this->assertNotNull($earning->voided_at, 'A COD earning is never paid out: the store already holds the cash.');
    }

    public function test_orders_delivered_before_the_store_accepted_the_fee_terms_are_not_charged(): void
    {
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: false);
        $sellerOrder = $this->shippedSellerOrder($store, '150.50');

        $this->actingAs($owner)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'delivered'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seller_earnings', ['seller_order_id' => $sellerOrder->id, 'commission_amount' => '0.00', 'net_amount' => '150.50']);
    }

    public function test_a_raised_rate_waits_for_the_store_to_accept_it_but_a_cut_applies_at_once(): void
    {
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        config(['marketplace.platform_fee.rate' => '0.02', 'marketplace.platform_fee.policy_version' => '3.0']);

        $beforeAccepting = $this->shippedSellerOrder($store, '100.00');
        $this->actingAs($owner)->patch(route('seller.orders.update', $beforeAccepting), ['status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_earnings', ['seller_order_id' => $beforeAccepting->id, 'commission_amount' => '1.00']);
        $this->actingAs($owner)->get(route('seller.platform-fees.index'))->assertOk()->assertSee('The Marketplace Fee Policy has changed');

        $this->actingAs($owner)->post(route('seller.platform-fees.accept'), ['accept' => '1'])->assertSessionHasNoErrors();
        $afterAccepting = $this->shippedSellerOrder($store, '100.00');
        $this->actingAs($owner)->patch(route('seller.orders.update', $afterAccepting), ['status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_earnings', ['seller_order_id' => $afterAccepting->id, 'commission_amount' => '2.00']);

        config(['marketplace.platform_fee.rate' => '0.005']);
        $afterCut = $this->shippedSellerOrder($store, '100.00');
        $this->actingAs($owner)->patch(route('seller.orders.update', $afterCut), ['status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_earnings', ['seller_order_id' => $afterCut->id, 'commission_amount' => '0.50']);
    }

    public function test_monthly_billing_charges_unbilled_fees_less_refund_credits_and_runs_only_once(): void
    {
        $this->travelTo('2026-09-20 10:00:00');
        [$store] = $this->storeWithOwner(acceptedFeeTerms: true);
        $refunded = $this->earning($store, '500.00');
        $this->earning($store, '200.00');
        $this->refund($refunded->sellerOrder, '100.00');
        $this->travelTo('2026-10-01 01:00:00');
        $deliveredInOctober = $this->earning($store, '300.00');

        $this->artisan('platform-fees:bill')->assertSuccessful();
        $this->artisan('platform-fees:bill')->assertSuccessful();

        $statement = PlatformFeeStatement::query()->sole();
        $this->assertSame('2026-09-01', $statement->period_start->toDateString());
        $this->assertSame('700.00', $statement->gross_total);
        $this->assertSame('7.00', $statement->commission_total);
        $this->assertSame('1.00', $statement->refund_credit);
        $this->assertSame('6.00', $statement->amount_due);
        $this->assertSame('unpaid', $statement->status);
        $this->assertSame('2026-10-15 23:59:59', $statement->due_at->toDateTimeString(), 'Due 15 days after September ends, through the end of that day.');
        $this->assertSame(2, $statement->earnings()->count());
        $this->assertNull($deliveredInOctober->fresh()->platform_fee_statement_id);
    }

    public function test_refund_credit_larger_than_a_months_fees_carries_into_the_next_statement(): void
    {
        $this->travelTo('2026-09-10 10:00:00');
        [$store] = $this->storeWithOwner(acceptedFeeTerms: true);
        $billedInSeptember = $this->earning($store, '500.00');
        $this->travelTo('2026-10-01 01:00:00');
        $this->artisan('platform-fees:bill')->assertSuccessful();

        $this->travelTo('2026-10-12 10:00:00');
        $this->refund($billedInSeptember->sellerOrder, '500.00');
        $this->earning($store, '200.00');
        $this->travelTo('2026-11-01 01:00:00');
        $this->artisan('platform-fees:bill')->assertSuccessful();

        $october = PlatformFeeStatement::query()->whereDate('period_start', '2026-10-01')->sole();
        $this->assertSame('5.00', $october->refund_credit);
        $this->assertSame('0.00', $october->amount_due);
        $this->assertSame('3.00', $october->credit_carried_out);
        $this->assertSame('paid', $october->status, 'Nothing is owed, so the statement never becomes overdue.');

        $this->travelTo('2026-11-15 10:00:00');
        $this->earning($store, '400.00');
        $this->travelTo('2026-12-01 01:00:00');
        $this->artisan('platform-fees:bill')->assertSuccessful();

        $november = PlatformFeeStatement::query()->whereDate('period_start', '2026-11-01')->sole();
        $this->assertSame('3.00', $november->credit_carried_in);
        $this->assertSame('1.00', $november->amount_due);
    }

    public function test_a_refund_from_a_month_that_was_never_billed_is_credited_on_the_next_statement(): void
    {
        $this->travelTo('2026-08-10 10:00:00');
        [$store] = $this->storeWithOwner(acceptedFeeTerms: true);
        $refunded = $this->earning($store, '500.00');
        $this->travelTo('2026-09-01 01:00:00');
        $this->artisan('platform-fees:bill')->assertSuccessful();

        $this->travelTo('2026-09-15 10:00:00');
        $this->refund($refunded->sellerOrder, '200.00');
        $this->travelTo('2026-10-12 10:00:00');
        $this->earning($store, '400.00');
        $this->travelTo('2026-11-01 01:00:00');
        $this->artisan('platform-fees:bill')->assertSuccessful();
        $this->artisan('platform-fees:bill', ['month' => '2026-09'])->assertSuccessful();

        $october = PlatformFeeStatement::query()->whereDate('period_start', '2026-10-01')->sole();
        $this->assertSame('2.00', $october->refund_credit, 'September was skipped, so its refund lands on October.');
        $this->assertSame('2.00', $october->amount_due);
        $this->assertFalse(PlatformFeeStatement::query()->whereDate('period_start', '2026-09-01')->exists(), 'A late run for a month before the last statement issues nothing.');
    }

    public function test_one_failed_statement_email_does_not_stop_the_others(): void
    {
        $this->travelTo('2026-09-20 10:00:00');
        [$firstStore] = $this->storeWithOwner(acceptedFeeTerms: true);
        [$secondStore] = $this->storeWithOwner(acceptedFeeTerms: true);
        $this->earning($firstStore, '500.00');
        $this->earning($secondStore, '500.00');
        $mailer = $this->mock(GmailOrderMailer::class);
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('Gmail is down.'));
        $mailer->shouldReceive('send')->once();
        $this->travelTo('2026-10-01 01:00:00');

        $this->artisan('platform-fees:bill')->assertSuccessful();

        $this->assertSame(2, PlatformFeeStatement::query()->count());
    }

    public function test_the_fee_page_estimates_the_next_statement_net_of_refund_credit_and_lists_billed_orders(): void
    {
        $this->travelTo('2026-09-10 10:00:00');
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $billed = $this->earning($store, '500.00');
        $this->travelTo('2026-10-01 01:00:00');
        $this->artisan('platform-fees:bill')->assertSuccessful();
        $this->travelTo('2026-10-12 10:00:00');
        $this->earning($store, '300.00');
        $this->refund($billed->sellerOrder, '100.00');

        $this->actingAs($owner)->get(route('seller.platform-fees.index'))
            ->assertOk()
            ->assertSeeInOrder(['Next statement so far', '₱3.00', 'Refund credit', '−₱1.00', 'Estimated amount due', '₱2.00'])
            ->assertSee('1 order on this statement')
            ->assertSee('₱5.00 open across 1 statement');
    }

    public function test_the_store_is_emailed_when_support_sends_its_payment_back(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $statement = PlatformFeeStatement::factory()->submitted()->create(['tenant_id' => $store->tenant_id]);
        $this->mock(GmailOrderMailer::class)->shouldReceive('send')->once()
            ->withArgs(fn (User $recipient, string $subject, string $body): bool => $recipient->is($owner) && str_contains($body, 'The receipt is unreadable.'));

        $this->actingAs($administrator)->patch(route('admin.platform-fees.update', $statement), ['action' => 'reject', 'note' => 'The receipt is unreadable.'])->assertSessionHasNoErrors();
    }

    public function test_one_failed_overdue_reminder_does_not_stop_the_others(): void
    {
        [$firstStore] = $this->storeWithOwner(acceptedFeeTerms: true);
        [$secondStore] = $this->storeWithOwner(acceptedFeeTerms: true);
        $first = PlatformFeeStatement::factory()->overdue()->create(['tenant_id' => $firstStore->tenant_id]);
        $second = PlatformFeeStatement::factory()->overdue()->create(['tenant_id' => $secondStore->tenant_id]);
        $mailer = $this->mock(GmailOrderMailer::class);
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('Mailbox does not exist.'));
        $mailer->shouldReceive('send');

        $this->artisan('platform-fees:remind')->assertSuccessful();

        $this->assertNotNull($first->fresh()->last_reminded_at);
        $this->assertNotNull($second->fresh()->last_reminded_at);
    }

    public function test_the_dashboard_tells_the_store_its_fee_is_due(): void
    {
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        PlatformFeeStatement::factory()->overdue()->create(['tenant_id' => $store->tenant_id, 'amount_due' => '45.60']);

        $this->actingAs($owner)->get(route('seller.dashboard'))->assertOk()->assertSee('Overdue: ₱45.60 platform fee')->assertSee('Pay fee');
    }

    public function test_support_marks_a_statement_paid_offline_with_a_reason(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $statement = PlatformFeeStatement::factory()->overdue()->create(['tenant_id' => $store->tenant_id]);

        $this->actingAs($administrator)->patch(route('admin.platform-fees.update', $statement), ['action' => 'mark_paid'])->assertSessionHasErrors('note');
        $this->actingAs($administrator)->patch(route('admin.platform-fees.update', $statement), ['action' => 'mark_paid', 'note' => 'Paid cash at the office, OR #1234'])->assertSessionHasNoErrors();

        $statement->refresh();
        $this->assertSame('paid', $statement->status);
        $this->assertSame('Paid cash at the office, OR #1234', $statement->payment_reference);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $statement->id, 'action' => 'platform_fee.marked_paid']);
        $this->actingAs($owner)->get(route('seller.platform-fees.index'))->assertOk()->assertSee('Paid cash at the office')->assertDontSee('View receipt');
    }

    public function test_support_is_emailed_when_a_store_submits_a_payment(): void
    {
        Storage::fake('chat_attachments');
        $administrator = User::factory()->administrator()->create();
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $statement = PlatformFeeStatement::factory()->create(['tenant_id' => $store->tenant_id]);
        $this->mock(GmailOrderMailer::class)->shouldReceive('send')->once()
            ->withArgs(fn (User $recipient, string $subject, string $body): bool => $recipient->is($administrator) && str_contains($body, 'GCASH-0003'));

        $this->actingAs($owner)->post(route('seller.platform-fees.pay', $statement), ['reference' => 'GCASH-0003', 'proof' => UploadedFile::fake()->image('receipt.jpg')])->assertSessionHasNoErrors();
    }

    public function test_support_confirms_a_payment_without_writing_a_note(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store] = $this->storeWithOwner(acceptedFeeTerms: true);
        $statement = PlatformFeeStatement::factory()->submitted()->create(['tenant_id' => $store->tenant_id]);

        $this->actingAs($administrator)->patch(route('admin.platform-fees.update', $statement), ['action' => 'confirm', 'note' => 'ok'])->assertSessionHasNoErrors();

        $this->assertSame('paid', $statement->fresh()->status);
    }

    public function test_a_store_pays_its_statement_and_support_confirms_it(): void
    {
        Storage::fake('chat_attachments');
        $administrator = User::factory()->administrator()->create();
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $statement = PlatformFeeStatement::factory()->create(['tenant_id' => $store->tenant_id]);

        $this->actingAs($owner)->post(route('seller.platform-fees.pay', $statement), ['reference' => 'GCASH-0001', 'proof' => UploadedFile::fake()->image('receipt.jpg')])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->patch(route('admin.platform-fees.update', $statement), ['action' => 'reject', 'note' => 'The receipt shows a different amount.'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('platform_fee_statements', ['id' => $statement->id, 'status' => 'unpaid', 'review_note' => 'The receipt shows a different amount.']);
        $this->actingAs($owner)->get(route('seller.platform-fees.index'))->assertOk()->assertSee('The receipt shows a different amount.');

        $this->actingAs($owner)->post(route('seller.platform-fees.pay', $statement), ['reference' => 'GCASH-0002', 'proof' => UploadedFile::fake()->image('receipt.jpg')])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->get(route('admin.platform-fees.index', ['status' => 'submitted']))->assertOk()->assertSee('GCASH-0002')->assertSee('Confirm payment received');
        $this->actingAs($administrator)->patch(route('admin.platform-fees.update', $statement), ['action' => 'confirm'])->assertSessionHasNoErrors();

        $statement->refresh();
        $this->assertSame('paid', $statement->status);
        $this->assertSame($administrator->id, $statement->confirmed_by);
        $this->assertSame('GCASH-0002', $statement->payment_reference);
        Storage::disk('chat_attachments')->assertExists($statement->payment_proof_path);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $statement->id, 'action' => 'platform_fee.payment_confirmed']);
    }

    public function test_a_payment_needs_a_reference_and_a_receipt(): void
    {
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $statement = PlatformFeeStatement::factory()->create(['tenant_id' => $store->tenant_id]);

        $this->actingAs($owner)->post(route('seller.platform-fees.pay', $statement), [])
            ->assertSessionHasErrors(['reference' => 'The reference field is required.', 'proof' => 'The proof field is required.']);
        $this->assertDatabaseHas('platform_fee_statements', ['id' => $statement->id, 'status' => 'unpaid']);
    }

    public function test_support_must_say_why_a_payment_is_sent_back(): void
    {
        $administrator = User::factory()->administrator()->create();
        [$store] = $this->storeWithOwner(acceptedFeeTerms: true);
        $statement = PlatformFeeStatement::factory()->submitted()->create(['tenant_id' => $store->tenant_id]);

        $this->actingAs($administrator)->patch(route('admin.platform-fees.update', $statement), ['action' => 'reject'])->assertSessionHasErrors('note');
        $this->assertDatabaseHas('platform_fee_statements', ['id' => $statement->id, 'status' => 'submitted']);
    }

    public function test_a_store_cannot_see_or_pay_another_stores_statement(): void
    {
        Storage::fake('chat_attachments');
        [$store] = $this->storeWithOwner(acceptedFeeTerms: true);
        [, $otherOwner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $statement = PlatformFeeStatement::factory()->submitted()->create(['tenant_id' => $store->tenant_id, 'amount_due' => '987.65']);
        Storage::disk('chat_attachments')->put($statement->payment_proof_path, 'receipt');

        $this->actingAs($otherOwner)->get(route('seller.platform-fees.index'))->assertOk()->assertDontSee('987.65');
        $this->actingAs($otherOwner)->get(route('seller.platform-fees.proof', $statement))->assertNotFound();
        $this->actingAs($otherOwner)->post(route('seller.platform-fees.pay', $statement), ['reference' => 'X', 'proof' => UploadedFile::fake()->image('receipt.jpg')])->assertNotFound();
    }

    public function test_only_the_owner_of_an_existing_store_accepts_the_fee_terms(): void
    {
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: false);
        $application = $owner->sellerApplications()->create(['store_name' => $store->name, 'status' => 'approved', 'tenant_id' => $store->tenant_id]);
        $staff = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $store->tenant_id, 'user_id' => $staff->id, 'role' => 'staff']);

        $this->actingAs($staff)->post(route('seller.platform-fees.accept'), ['accept' => '1'])->assertForbidden();
        $this->assertNull($store->tenant->fresh()->platform_fee_accepted_at);

        $this->actingAs($owner)->get(route('seller.dashboard'))->assertOk()->assertSee('accept the Marketplace Fee Policy');
        $this->actingAs($owner)->post(route('seller.platform-fees.accept'), ['accept' => '1'])->assertSessionHasNoErrors();

        $this->assertNotNull($store->tenant->fresh()->platform_fee_accepted_at);
        $this->assertTrue($application->policyAcceptances()->where('policy_type', 'fees')->where('version', '2.0')->exists());
        $this->actingAs($owner)->get(route('seller.dashboard'))->assertOk()->assertDontSee('accept the Marketplace Fee Policy');
    }

    public function test_overdue_statements_are_reminded_at_most_every_three_days(): void
    {
        [$store, $owner] = $this->storeWithOwner(acceptedFeeTerms: true);
        $overdue = PlatformFeeStatement::factory()->overdue()->create(['tenant_id' => $store->tenant_id]);
        PlatformFeeStatement::factory()->create(['tenant_id' => $store->tenant_id, 'period_start' => now()->startOfMonth()->toDateString(), 'due_at' => now()->addDays(5)]);
        $this->mock(GmailOrderMailer::class)->shouldReceive('send')->withArgs(fn (User $recipient): bool => $recipient->is($owner))->twice();

        $this->artisan('platform-fees:remind')->assertSuccessful();
        $this->artisan('platform-fees:remind')->assertSuccessful();
        $this->travel(4)->days();
        $this->artisan('platform-fees:remind')->assertSuccessful();

        $this->assertNotNull($overdue->fresh()->last_reminded_at);
    }

    /**
     * @return array{0: Store, 1: User}
     */
    private function storeWithOwner(bool $acceptedFeeTerms): array
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active', 'platform_fee_accepted_at' => $acceptedFeeTerms ? now()->subYear() : null, 'platform_fee_policy_version' => $acceptedFeeTerms ? '2.0' : null, 'platform_fee_rate' => $acceptedFeeTerms ? '0.01' : null]);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $owner = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$store, $owner];
    }

    private function shippedSellerOrder(Store $store, string $total): SellerOrder
    {
        $buyer = User::factory()->create();
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'JM-'.str()->upper(str()->random(8)), 'status' => 'shipped', 'recipient_name' => $buyer->name, 'phone' => '+639171234567', 'address_line' => '100 Market Street', 'city' => 'Manila', 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total]);
        $order->payment()->create(['method' => 'cod', 'amount' => $total]);

        return $order->sellerOrders()->create(['tenant_id' => $store->tenant_id, 'store_id' => $store->id, 'status' => 'shipped', 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total]);
    }

    /**
     * A store order delivered now, with its earning charged at the 1% rate.
     */
    private function earning(Store $store, string $gross): SellerEarning
    {
        $sellerOrder = $this->shippedSellerOrder($store, $gross);
        $sellerOrder->update(['status' => 'delivered', 'delivered_at' => now()]);
        $commission = bcmul($gross, '0.01', 2);

        return SellerEarning::create(['seller_order_id' => $sellerOrder->id, 'tenant_id' => $store->tenant_id, 'store_id' => $store->id, 'gross_amount' => $gross, 'commission_rate' => '0.01', 'commission_amount' => $commission, 'net_amount' => bcsub($gross, $commission, 2), 'status' => 'available', 'available_at' => now(), 'voided_at' => now()]);
    }

    private function refund(SellerOrder $sellerOrder, string $amount): void
    {
        $buyer = $sellerOrder->order()->firstOrFail()->user()->firstOrFail();
        $case = ReturnCase::create(['seller_order_id' => $sellerOrder->id, 'buyer_id' => $buyer->id, 'opened_by' => $buyer->id, 'status' => 'awaiting_confirmation', 'reason' => 'damaged', 'requested_remedy' => 'refund', 'remedy' => 'refund', 'details' => 'The item arrived broken.']);
        $case->outcome()->create(['recorded_by' => $buyer->id, 'kind' => 'refund', 'amount' => $amount, 'paid_at' => now(), 'created_at' => now()]);
    }
}
