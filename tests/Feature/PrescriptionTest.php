<?php

namespace Tests\Feature;

use App\Actions\BranchInventory;
use App\Models\BranchStock;
use App\Models\Cart;
use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Store $branch;

    private Product $medicine;

    private MockInterface $push;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('chat_attachments');
        $this->push = Mockery::spy(PushNotifier::class);
        $this->app->instance(PushNotifier::class, $this->push);
        config()->set('services.paymongo', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsk_test', 'base_url' => 'https://api.paymongo.com']);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_1']]]),
            'api.paymongo.com/v1/refunds' => Http::response(['data' => ['id' => 'ref_1']]),
        ]);

        $this->tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active']);
        $this->branch = $this->branch('Makati');
        $this->medicine = Product::create([
            'tenant_id' => $this->tenant->id, 'store_id' => $this->branch->id, 'name' => 'Amoxicillin 500mg', 'slug' => 'amoxicillin-500mg',
            'sku' => 'AMOX-500', 'price' => '20.00', 'stock_quantity' => 0, 'status' => 'active', 'requires_prescription' => true,
        ]);
        BranchStock::query()->create(['store_id' => $this->branch->id, 'product_id' => $this->medicine->id, 'variant_key' => 'default', 'quantity' => 5]);
        app(BranchInventory::class)->syncTotals($this->medicine);
    }

    public function test_a_prescription_medicine_cannot_be_checked_out_without_a_prescription(): void
    {
        $buyer = $this->buyerWithCart();

        $this->actingAs($buyer)->get(route('checkout.create'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Amoxicillin 500mg needs a doctor’s prescription.');
        $this->actingAs($buyer)->post(route('checkout.store'), $this->delivery())->assertSessionHasErrors('prescription');
        $this->actingAs($buyer)->postJson(route('api.v1.checkout.store'), [...$this->delivery(), 'delivery_latitude' => 14.55, 'delivery_longitude' => 121.02])
            ->assertUnprocessable()->assertJsonPath('error.fields.prescription.0', 'Upload a photo of your prescription for Amoxicillin 500mg.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, BranchInventory::available($this->branch->id, $this->medicine->id, null));
    }

    public function test_checkout_stores_the_prescription_privately_and_the_order_waits_for_a_pharmacist(): void
    {
        $order = $this->checkOut();
        $prescription = $order->prescription;

        $this->assertSame('pending', $order->sellerOrders()->sole()->status);
        $this->assertSame('pending', $prescription->status);
        $image = $prescription->images()->sole();
        $this->assertSame('rx.jpg', $image->original_name);
        Storage::disk('chat_attachments')->assertExists($image->path);
        $this->actingAs($order->user)->get(route('orders.placed', $order))->assertSee('A pharmacist checks your prescription first.');
        $this->actingAs($order->user)->get(route('orders.show', $order))->assertSee('Waiting for pharmacist review');
        $this->actingAs($order->user)->get(route('orders.show', $order), ['X-Order-Modal' => '1'])->assertSee('Prescription in review');
    }

    public function test_a_checkout_that_fails_after_saving_the_prescription_removes_the_file(): void
    {
        Prescription::creating(fn () => throw new RuntimeException('The database went away.'));

        $this->actingAs($this->buyerWithCart())
            ->post(route('checkout.store'), [...$this->delivery(), 'prescription' => UploadedFile::fake()->image('rx.jpg')])
            ->assertServerError();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame([], Storage::disk('chat_attachments')->allFiles());
    }

    public function test_branch_staff_are_told_a_new_order_has_a_prescription_to_review(): void
    {
        $pharmacist = $this->member('staff', $this->branch, pharmacist: true);
        $order = $this->checkOut();

        $this->assertPushed("New order #{$order->number} · prescription to review", "{$this->branch->name} received a new order worth PHP {$order->sellerOrders()->sole()->total}. A pharmacist must check the prescription before it can be prepared.");
        $this->assertSame("New order #{$order->number} · prescription to review", $pharmacist->notifications()->sole()->data['title']);
    }

    public function test_the_order_waits_for_a_pharmacist_and_approving_accepts_it(): void
    {
        $sellerOrder = $this->checkOut()->sellerOrders()->sole();
        $manager = $this->member('manager', $this->branch);
        $pharmacist = $this->member('staff', $this->branch, pharmacist: true);

        $this->actingAs($manager)->get(route('seller.orders.index', ['status' => 'pending']))->assertSee('Prescription to review')->assertDontSee('Confirm all pending');
        $this->actingAs($manager)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $sellerOrder->refresh()->status);

        $this->actingAs($pharmacist)->get(route('seller.orders.show', $sellerOrder))->assertSee('Approve prescription');
        $this->actingAs($pharmacist)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'approve'])->assertSessionHas('status');
        $this->actingAs($manager)->get(route('seller.orders.show', $sellerOrder))->assertSee('Approved by '.$pharmacist->name.' (PRC license 0123456)');

        $this->assertSame('confirmed', $sellerOrder->refresh()->status);
        $this->assertDatabaseHas('prescriptions', ['order_id' => $sellerOrder->order_id, 'status' => 'approved', 'reviewed_by' => $pharmacist->id, 'reviewer_license_number' => '0123456']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $pharmacist->id, 'action' => 'prescription.approved']);
        $this->assertPushed('Prescription approved · your order is being prepared');
    }

    public function test_rejecting_a_paid_prescription_order_cancels_it_restores_branch_stock_and_refunds(): void
    {
        $order = $this->checkOut(['payment_method' => 'gcash']);
        $this->get(route('orders.pay', $order));
        $this->paymentWebhook();
        $sellerOrder = $order->sellerOrders()->sole();
        $this->assertSame(3, BranchInventory::available($this->branch->id, $this->medicine->id, null));

        $this->actingAs($this->member('owner', null, pharmacist: true))
            ->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'reject', 'reason' => 'The prescription has expired.'])
            ->assertSessionHas('status');

        $this->assertSame('cancelled', $sellerOrder->refresh()->status);
        $this->assertSame(5, BranchInventory::available($this->branch->id, $this->medicine->id, null));
        $this->assertDatabaseHas('prescriptions', ['id' => $order->prescription->id, 'status' => 'rejected', 'reason' => 'The prescription has expired.']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prescription.rejected']);
        $this->assertPushed('Prescription not accepted · order cancelled', 'The prescription has expired.');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/refunds') && $request['data']['attributes']['amount'] === 4000);
    }

    public function test_a_pharmacist_can_ask_for_a_clearer_photo_and_the_customer_resends_it_from_the_app(): void
    {
        $order = $this->checkOut();
        $sellerOrder = $order->sellerOrders()->sole();
        $firstPath = $order->prescription->images()->sole()->path;
        $pharmacist = $this->member('staff', $this->branch, pharmacist: true);

        $this->actingAs($pharmacist)
            ->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'needs_photo', 'reason' => 'The photo is blurry.'])
            ->assertSessionHas('status', 'The customer was asked for a clearer photo.');

        $this->assertSame('pending', $sellerOrder->refresh()->status);
        $this->assertPushed('Send a clearer prescription photo', 'The photo is blurry.');
        $this->assertStringContainsString('The photo is blurry.', $order->user->notifications()->where('data->type', 'order')->sole()->data['body']);
        $chat = ConversationMessage::query()->where('sender_id', $pharmacist->id)->sole();
        $this->assertStringContainsString('The photo is blurry.', $chat->body);
        $this->assertTrue($chat->conversation->buyer_id === $order->user_id && $chat->conversation->store_id === $sellerOrder->store_id);
        $this->push->shouldHaveReceived('sendToUser')->withArgs(fn ($user, string $title, string $body, array $data): bool => $user->is($order->user) && $data['type'] === 'chat')->once();
        $this->actingAs($this->member('manager', $this->branch))->patch(route('seller.orders.update', $sellerOrder), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->get(route('seller.orders.index'))->assertSee('Waiting for a clearer photo')->assertDontSee('Prescription to review');
        $this->actingAs($order->user)->getJson(route('api.v1.orders.show', $order))
            ->assertJsonPath('data.prescription.status', 'needs_photo')
            ->assertJsonPath('data.prescription.reason', 'The photo is blurry.');

        $this->actingAs($order->user)
            ->post(route('api.v1.orders.prescription.store', $order), ['prescription' => UploadedFile::fake()->image('clear.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.prescription.status', 'pending')
            ->assertJsonPath('data.prescription.reason', null);

        $prescription = $order->prescription()->first();
        $image = $prescription->images()->sole();
        $this->assertSame('clear.jpg', $image->original_name);
        Storage::disk('chat_attachments')->assertExists($image->path);
        Storage::disk('chat_attachments')->assertMissing($firstPath);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $order->user_id, 'action' => 'prescription.resubmitted']);
        $this->push->shouldHaveReceived('sendToUser')
            ->withArgs(fn ($user, string $title): bool => $user->is($pharmacist) && $title === "New prescription photo for order #{$order->number}")
            ->once();
        $this->assertTrue($pharmacist->notifications()->exists());

        $this->actingAs($order->user)
            ->post(route('api.v1.orders.prescription.store', $order), ['prescription' => UploadedFile::fake()->image('again.jpg')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_only_the_customer_can_resend_a_prescription_photo(): void
    {
        $order = $this->checkOut();
        $this->actingAs($this->member('staff', $this->branch, pharmacist: true))
            ->patch(route('seller.orders.prescription.update', $order->sellerOrders()->sole()), ['decision' => 'needs_photo', 'reason' => 'Blurry.']);

        $this->actingAs(User::factory()->create())
            ->post(route('orders.prescription.store', $order), ['prescription' => UploadedFile::fake()->image('x.jpg')])
            ->assertNotFound();

        $this->actingAs($order->user)->get(route('orders.show', $order))->assertSee('Clearer photo needed')->assertSee('Send new photos');
        $this->actingAs($order->user)
            ->post(route('orders.prescription.store', $order), ['prescription' => UploadedFile::fake()->image('clear.jpg')])
            ->assertRedirect(route('orders.show', $order));
        $this->assertSame('pending', $order->prescription()->first()->status);
    }

    public function test_staff_at_the_orders_branch_read_the_prescription_but_only_pharmacists_decide(): void
    {
        $sellerOrder = $this->checkOut()->sellerOrders()->sole();
        $otherBranchPharmacist = $this->member('staff', $this->branch('Cebu'), pharmacist: true);
        $staff = $this->member('manager', $this->branch);
        $image = Prescription::query()->sole()->images()->sole();

        $this->actingAs($otherBranchPharmacist)->get(route('seller.orders.prescription.images.show', [$sellerOrder, $image]))->assertNotFound();
        $this->actingAs($otherBranchPharmacist)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'approve'])->assertNotFound();
        $this->actingAs($staff)->get(route('seller.orders.prescription.images.show', [$sellerOrder, $image]))->assertOk();
        $this->actingAs($staff)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($staff)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'reject', 'reason' => 'Expired.'])->assertForbidden();

        $this->assertDatabaseHas('prescriptions', ['order_id' => $sellerOrder->order_id, 'status' => 'pending', 'reviewed_by' => null]);
        $this->actingAs($this->member('staff', $this->branch, pharmacist: true))->get(route('seller.orders.prescription.images.show', [$sellerOrder, $image]))->assertOk();

        $this->actingAs($staff)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'needs_photo', 'reason' => 'Blurry.'])
            ->assertSessionHas('status', 'The customer was asked for a clearer photo.');
        $this->assertDatabaseHas('prescriptions', ['order_id' => $sellerOrder->order_id, 'status' => 'needs_photo', 'reviewed_by' => $staff->id, 'reviewer_license_number' => null]);
        $this->assertPushed('Send a clearer prescription photo', 'Blurry.');
    }

    public function test_a_pharmacist_without_a_valid_prc_license_cannot_decide_a_prescription(): void
    {
        $sellerOrder = $this->checkOut()->sellerOrders()->sole();
        $image = Prescription::query()->sole()->images()->sole();
        $unlicensed = $this->member('staff', $this->branch, pharmacist: true);
        $unlicensed->tenantMemberships()->update(['license_number' => null]);
        $expired = $this->member('staff', $this->branch, pharmacist: true);
        $expired->tenantMemberships()->update(['license_expires_on' => today()->subDay()]);

        foreach ([$unlicensed, $expired] as $pharmacist) {
            $this->actingAs($pharmacist)->get(route('seller.orders.show', $sellerOrder))
                ->assertSee('Your PRC license is missing or expired')
                ->assertDontSee('Approve prescription');
            $this->actingAs($pharmacist)->get(route('seller.orders.prescription.images.show', [$sellerOrder, $image]))->assertOk();
            $this->actingAs($pharmacist)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'approve'])->assertForbidden();
        }

        $this->assertDatabaseHas('prescriptions', ['order_id' => $sellerOrder->order_id, 'status' => 'pending']);
    }

    public function test_branch_staff_pre_check_a_prescription_and_a_pharmacist_still_approves_it(): void
    {
        $order = $this->checkOut();
        $sellerOrder = $order->sellerOrders()->sole();
        $staff = $this->member('staff', $this->branch);
        $pharmacist = $this->member('staff', $this->branch, pharmacist: true);

        $this->actingAs($this->member('staff', $this->branch('Cebu')))->post(route('seller.orders.prescription.check', $sellerOrder))->assertNotFound();
        $this->actingAs($pharmacist)->get(route('seller.orders.show', $sellerOrder))->assertDontSee('Mark as checked');
        $this->actingAs($staff)->get(route('seller.orders.show', $sellerOrder))->assertSee('Mark as checked')->assertDontSee('Approve prescription');

        $this->actingAs($staff)->post(route('seller.orders.prescription.check', $sellerOrder))->assertSessionHas('status');
        $this->actingAs($staff)->post(route('seller.orders.prescription.check', $sellerOrder))->assertSessionHasErrors('prescription');

        $this->assertSame('pending', $sellerOrder->refresh()->status);
        $this->assertDatabaseHas('prescriptions', ['order_id' => $order->id, 'status' => 'pending', 'checked_by' => $staff->id, 'reviewed_by' => null]);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $staff->id, 'action' => 'prescription.checked']);
        $this->actingAs($pharmacist)->get(route('seller.orders.index'))->assertSee('Checked · pharmacist to approve');
        $this->actingAs($pharmacist)->get(route('seller.orders.show', $sellerOrder))->assertSee('Checked by '.$staff->name)->assertSee('Approve prescription');
        $this->actingAs($order->user)->getJson(route('api.v1.orders.show', $order))->assertJsonMissing(['action' => 'prescription.checked']);

        // A new photo from the customer needs a fresh check.
        $this->actingAs($pharmacist)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'needs_photo', 'reason' => 'Blurry.']);
        $this->actingAs($order->user)->post(route('orders.prescription.store', $order), ['prescription' => UploadedFile::fake()->image('clear.jpg')]);
        $this->assertDatabaseHas('prescriptions', ['order_id' => $order->id, 'status' => 'pending', 'checked_by' => null, 'checked_at' => null]);
    }

    public function test_checkout_stores_every_prescription_page_in_order(): void
    {
        $this->actingAs($this->buyerWithCart())
            ->post(route('checkout.store'), [
                ...$this->delivery(),
                'prescriptions' => [
                    UploadedFile::fake()->image('page-1.jpg'),
                    UploadedFile::fake()->image('page-2.jpg'),
                    UploadedFile::fake()->image('page-3.jpg'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $prescription = Order::query()->latest()->firstOrFail()->prescription;
        $images = $prescription->images()->get();

        $this->assertSame(['page-1.jpg', 'page-2.jpg', 'page-3.jpg'], $images->pluck('original_name')->all());
        $this->assertSame([0, 1, 2], $images->pluck('position')->all());

        foreach ($images as $image) {
            Storage::disk('chat_attachments')->assertExists($image->path);
        }
    }

    public function test_the_prescription_timeline_records_every_review_event(): void
    {
        $order = $this->checkOut();
        $sellerOrder = $order->sellerOrders()->sole();
        $pharmacist = $this->member('staff', $this->branch, pharmacist: true);

        $this->actingAs($pharmacist)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'needs_photo', 'reason' => 'Blurry.']);
        $this->actingAs($order->user)->post(route('orders.prescription.store', $order), [
            'prescriptions' => [
                UploadedFile::fake()->image('page-1.jpg'),
                UploadedFile::fake()->image('page-2.jpg'),
            ],
        ])->assertRedirect(route('orders.show', $order));
        $this->actingAs($pharmacist)->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'approve']);

        $prescription = $order->prescription()->firstOrFail();

        $this->assertSame([
            'prescription.submitted',
            'prescription.needs_photo',
            'prescription.resubmitted',
            'prescription.approved',
        ], $prescription->timeline()->pluck('action')->all());
        $this->assertCount(2, $prescription->images);
    }

    public function test_combined_prescription_and_discount_documents_keep_their_reviews_separate(): void
    {
        $this->medicine->update(['generic_name' => 'Amoxicillin']);
        $order = $this->checkOut([
            'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-5501', 'name' => 'Patient'],
            'discount_document' => UploadedFile::fake()->image('pwd.jpg'),
        ]);
        $sellerOrder = $order->sellerOrders()->sole();
        $staff = $this->member('staff', $this->branch);
        $before = (string) $order->total;

        $this->actingAs($staff)->get(route('seller.orders.index', ['review' => 'prescription']))->assertSee($order->number);
        $this->get(route('seller.orders.index', ['review' => 'discount']))->assertSee($order->number);
        $this->actingAs($staff)->get(route('seller.orders.show', $sellerOrder), ['X-Order-Modal' => '1'])
            ->assertSee('Waiting for pharmacist approval')->assertSee('PWD ID')->assertSee('rx.jpg')
            ->assertSee('Mark ID reviewed')->assertDontSee('Approve prescription');
        $this->post(route('seller.orders.discount-document.review', $sellerOrder))->assertSessionHasNoErrors();
        $this->get(route('seller.orders.index', ['review' => 'discount']))->assertDontSee($order->number);
        $this->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('pending', $sellerOrder->refresh()->status);
        $this->assertSame('pending', $order->prescription->refresh()->status);
        $this->assertSame($before, (string) $order->refresh()->total);

        $image = $order->prescription->images()->sole();
        $this->get(route('seller.orders.prescription.images.show', [$sellerOrder, $image, 'preview' => 1]))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->member('staff', $this->branch, pharmacist: true))
            ->patch(route('seller.orders.prescription.update', $sellerOrder), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $sellerOrder->refresh()->status);
        $this->assertNotNull($sellerOrder->discount_reviewed_at);
        $this->get(route('seller.orders.index', ['review' => 'prescription']))->assertDontSee($order->number);
    }

    private function assertPushed(string $title, ?string $body = null): void
    {
        $this->push->shouldHaveReceived('sendToUser')
            ->withArgs(fn ($user, string $sentTitle, string $sentBody): bool => $sentTitle === $title && ($body === null || $sentBody === $body))
            ->once();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function checkOut(array $extra = []): Order
    {
        $this->actingAs($this->buyerWithCart())
            ->post(route('checkout.store'), [...$this->delivery(), ...$extra, 'prescription' => UploadedFile::fake()->image('rx.jpg')])
            ->assertSessionHasNoErrors();

        return Order::query()->with('prescription')->latest()->firstOrFail();
    }

    private function paymentWebhook(): void
    {
        $body = json_encode(['data' => ['id' => 'evt_paid', 'attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => [
            'id' => 'cs_1',
            'attributes' => ['payments' => [['id' => 'pay_1', 'attributes' => ['status' => 'paid']]]],
        ]]]]);
        $signature = hash_hmac('sha256', '1700000000.'.$body, 'whsk_test');

        $this->call('POST', route('paymongo.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t=1700000000,te={$signature},li="], $body)->assertOk();
    }

    private function buyerWithCart(): User
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        Cart::create(['user_id' => $buyer->id])->items()->create(['product_id' => $this->medicine->id, 'quantity' => 2]);

        return $buyer;
    }

    private function branch(string $city): Store
    {
        return Store::create([
            'tenant_id' => $this->tenant->id, 'name' => "Botika Plus {$city}", 'slug' => 'botika-plus-'.str($city)->slug(),
            'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => $city, 'status' => 'active',
        ]);
    }

    private function member(string $role, ?Store $branch, bool $pharmacist = false): User
    {
        $user = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $this->tenant->id, 'store_id' => $branch?->id, 'user_id' => $user->id, 'role' => $role, 'is_pharmacist' => $pharmacist, 'license_number' => $pharmacist ? '0123456' : null, 'license_expires_on' => $pharmacist ? today()->addYear() : null]);

        return $user;
    }

    /**
     * @return array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string}
     */
    private function delivery(): array
    {
        return ['recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'province' => 'Metro Manila', 'postal_code' => '1000'];
    }
}
