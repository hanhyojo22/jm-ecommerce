<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MarketplaceNotice;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CartReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cart_idle_for_a_day_is_reminded_once(): void
    {
        Notification::fake();
        [$buyer, $cart] = $this->cartWithItem(now()->subHours(25));

        $this->expectPushes(1);
        $this->artisan('carts:remind')->assertSuccessful();
        $this->artisan('carts:remind')->assertSuccessful();

        Notification::assertSentToTimes($buyer, MarketplaceNotice::class, 1);
        $this->assertNotNull($cart->fresh()->reminded_at);
    }

    public function test_a_recently_changed_cart_is_not_reminded(): void
    {
        Notification::fake();
        [$buyer] = $this->cartWithItem(now()->subHours(2));

        $this->expectPushes(0);
        $this->artisan('carts:remind')->assertSuccessful();

        Notification::assertNothingSentTo($buyer);
    }

    public function test_a_cart_changed_after_its_reminder_is_reminded_again_once_idle(): void
    {
        Notification::fake();
        [$buyer, $cart] = $this->cartWithItem(now()->subHours(26));
        $cart->timestamps = false;
        $cart->update(['reminded_at' => now()->subHours(28)]);

        $this->expectPushes(1);
        $this->artisan('carts:remind')->assertSuccessful();

        Notification::assertSentToTimes($buyer, MarketplaceNotice::class, 1);
    }

    public function test_removing_an_item_restarts_the_idle_clock(): void
    {
        Notification::fake();
        [$buyer, $cart] = $this->cartWithItem(now()->subHours(30));
        $extra = $cart->items()->first()->replicate(['id']);
        $extra->variant_key = 'other';
        $extra->saveQuietly();
        $this->idleSince($cart, now()->subHours(30));

        $extra->delete();

        $this->expectPushes(0);
        $this->artisan('carts:remind')->assertSuccessful();

        Notification::assertNothingSentTo($buyer);
    }

    public function test_buyers_who_turned_reminders_off_are_not_reminded(): void
    {
        Notification::fake();
        [$buyer] = $this->cartWithItem(now()->subHours(25));
        $buyer->update(['cart_reminders' => false]);

        $this->expectPushes(0);
        $this->artisan('carts:remind')->assertSuccessful();

        Notification::assertNothingSentTo($buyer);
    }

    public function test_a_cart_with_only_sold_out_items_is_not_reminded(): void
    {
        Notification::fake();
        [$buyer] = $this->cartWithItem(now()->subHours(25));
        Product::query()->update(['stock_quantity' => 0]);

        $this->expectPushes(0);
        $this->artisan('carts:remind')->assertSuccessful();

        Notification::assertNothingSentTo($buyer);
    }

    public function test_buyers_can_turn_cart_reminders_off_on_the_web_and_in_the_app(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->patch(route('profile.notifications.update'))->assertRedirect(route('profile.edit'));
        $this->assertFalse($buyer->fresh()->cart_reminders);

        $this->actingAs($buyer)->patchJson('/api/v1/me', ['cart_reminders' => true])
            ->assertOk()
            ->assertJsonPath('data.cart_reminders', true);
        $this->assertTrue($buyer->fresh()->cart_reminders);

        $this->actingAs($buyer)->patchJson('/api/v1/me', [])->assertUnprocessable();
    }

    private function expectPushes(int $times): void
    {
        $this->mock(PushNotifier::class, function (MockInterface $notifier) use ($times): void {
            $notifier->shouldReceive('sendToUser')->times($times)->with(Mockery::type(User::class), Mockery::any(), Mockery::any(), ['type' => 'cart']);
        });
    }

    private function idleSince(Cart $cart, mixed $lastActivity): void
    {
        $cart->timestamps = false;
        $cart->forceFill(['updated_at' => $lastActivity])->save();
        $cart->timestamps = true;
    }

    /**
     * @return array{0: User, 1: Cart}
     */
    private function cartWithItem(mixed $updatedAt): array
    {
        $buyer = User::factory()->create();
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Cart Tote',
            'slug' => "cart-tote-{$suffix}",
            'sku' => "CART-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);
        $cart = Cart::create(['user_id' => $buyer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_key' => 'default', 'quantity' => 1]);
        $this->idleSince($cart, $updatedAt);

        return [$buyer, $cart];
    }
}
