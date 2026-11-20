<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeliveryAddress;
use App\Models\DiscountCard;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_completes_their_profile(): void
    {
        $buyer = User::factory()->create(['name' => 'Franil']);

        $this->actingAs($buyer)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.profile_complete', false);

        $this->actingAs($buyer)->patchJson('/api/v1/me', [
            'name' => 'Franil Escalica', 'birth_date' => '1960-03-15', 'phone' => '09171234567', 'sex' => 'female',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Franil Escalica')
            ->assertJsonPath('data.birth_date', '1960-03-15')
            ->assertJsonPath('data.phone', '09171234567')
            ->assertJsonPath('data.sex', 'female')
            ->assertJsonPath('data.profile_complete', true);
        $this->assertNotNull($buyer->fresh()->name_confirmed_at);
    }

    public function test_profile_details_are_validated(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->patchJson('/api/v1/me', [
            'name' => '', 'birth_date' => now()->addDay()->toDateString(), 'phone' => '12345', 'sex' => 'other',
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['name', 'birth_date', 'phone', 'sex']]]);
    }

    public function test_older_apps_can_still_change_cart_reminders_alone(): void
    {
        $buyer = User::factory()->create(['name' => 'Franil']);

        $this->actingAs($buyer)->patchJson('/api/v1/me', ['cart_reminders' => false])->assertOk();

        $this->assertFalse($buyer->fresh()->cart_reminders);
        $this->assertSame('Franil', $buyer->fresh()->name);
        $this->assertNull($buyer->fresh()->name_confirmed_at);
    }

    public function test_a_clerk_update_keeps_a_name_the_customer_typed(): void
    {
        config()->set('services.clerk.webhook_signing_secret', 'whsec_'.base64_encode('test-secret'));
        $buyer = User::factory()->create(['clerk_user_id' => 'user_123', 'name' => 'Franil Escalica', 'name_confirmed_at' => now()]);
        $payload = json_encode(['type' => 'user.updated', 'data' => ['id' => 'user_123', 'first_name' => 'Franil', 'last_name' => null]], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();
        $signature = base64_encode(hash_hmac('sha256', 'msg_1.'.$timestamp.'.'.$payload, 'test-secret', true));

        $this->call('POST', route('clerk.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_SVIX_ID' => 'msg_1', 'HTTP_SVIX_TIMESTAMP' => $timestamp, 'HTTP_SVIX_SIGNATURE' => 'v1,'.$signature,
        ], $payload)->assertOk();

        $this->assertSame('Franil Escalica', $buyer->fresh()->name);
    }

    public function test_deleting_an_account_removes_personal_details_but_keeps_orders(): void
    {
        config()->set('services.clerk.secret_key', 'sk_test_123');
        Http::fake(['api.clerk.com/*' => Http::response(['deleted' => true])]);
        $buyer = User::factory()->create(['clerk_user_id' => 'user_123', 'phone' => '09171234567', 'birth_date' => '1960-03-15']);
        DeliveryAddress::factory()->for($buyer, 'user')->create();
        DiscountCard::factory()->for($buyer, 'user')->create();
        $order = $this->orderFor($buyer, 'delivered');

        $this->actingAs($buyer)->deleteJson('/api/v1/me')->assertNoContent();

        $buyer->refresh();
        $this->assertSame('Deleted customer', $buyer->name);
        $this->assertStringEndsWith('@deleted.invalid', $buyer->email);
        $this->assertFalse($buyer->is_active);
        $this->assertNull($buyer->clerk_user_id);
        $this->assertNull($buyer->phone);
        $this->assertNull($buyer->birth_date);
        $this->assertDatabaseCount('delivery_addresses', 0);
        $this->assertDatabaseCount('discount_cards', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $buyer->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'account.deleted')->where('entity_id', $buyer->id)->exists());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/users/user_123'));
    }

    public function test_an_account_with_an_order_in_progress_cannot_be_deleted(): void
    {
        Http::fake();
        $buyer = User::factory()->create(['name' => 'Ava Buyer']);
        $this->orderFor($buyer, 'pending');

        $this->actingAs($buyer)->deleteJson('/api/v1/me')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['account']]]);

        $this->assertSame('Ava Buyer', $buyer->fresh()->name);
        Http::assertNothingSent();
    }

    public function test_a_staff_account_cannot_delete_itself_from_the_app(): void
    {
        Http::fake();
        $staff = User::factory()->seller()->create(['name' => 'Branch Staff']);
        $tenant = Tenant::create(['name' => 'Everyday Pharmacy', 'slug' => 'everyday', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'staff']);

        $this->actingAs($staff)->deleteJson('/api/v1/me')->assertUnprocessable();

        $this->assertSame('Branch Staff', $staff->fresh()->name);
    }

    private function orderFor(User $buyer, string $status): Order
    {
        return Order::create([
            'user_id' => $buyer->id,
            'number' => 'EP-'.str()->upper(str()->random(8)),
            'status' => $status,
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => 100,
            'shipping_amount' => 0,
            'total' => 100,
        ]);
    }
}
