<?php

namespace Tests\Feature;

use App\Models\DeliveryAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_save_edit_and_remove_a_delivery_address(): void
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $address = [
            'recipient_name' => 'Ava Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
        ];

        $this->actingAs($seller)->post(route('delivery-addresses.store'), $address)
            ->assertRedirect(route('delivery-addresses.index'));
        $saved = DeliveryAddress::query()->sole();
        $this->assertSame($seller->id, $saved->user_id);
        $this->actingAs($seller)->get(route('delivery-addresses.index'))->assertOk()->assertSee('10 Market Street');

        $this->actingAs($seller)->patch(route('delivery-addresses.update', $saved), [
            ...$address,
            'address_line' => '20 Market Street',
        ])->assertRedirect(route('delivery-addresses.index'));
        $this->assertDatabaseHas('delivery_addresses', ['id' => $saved->id, 'address_line' => '20 Market Street']);

        $this->actingAs($seller)->delete(route('delivery-addresses.destroy', $saved))
            ->assertRedirect(route('delivery-addresses.index'));
        $this->assertDatabaseCount('delivery_addresses', 0);
    }

    public function test_delivery_address_requires_province_and_four_digit_postal_code(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($buyer)->post(route('delivery-addresses.store'), [
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'province' => '',
            'postal_code' => '123',
        ])->assertSessionHasErrors(['province', 'postal_code']);

        $this->assertDatabaseCount('delivery_addresses', 0);
    }

    public function test_a_shopper_cannot_read_or_change_another_accounts_address(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->seller()->create(['email_verified_at' => now()]);
        $address = DeliveryAddress::factory()->for($owner, 'user')->create();

        $this->actingAs($other)->get(route('delivery-addresses.index'))->assertOk()->assertDontSee($address->address_line);
        $this->actingAs($other)->get(route('delivery-addresses.edit', $address))->assertNotFound();
        $this->actingAs($other)->patch(route('delivery-addresses.update', $address), [
            'recipient_name' => 'Changed', 'phone' => '+639171234567', 'address_line' => 'Other place', 'city' => 'Manila',
        ])->assertNotFound();
        $this->actingAs($other)->delete(route('delivery-addresses.destroy', $address))->assertNotFound();
        $this->assertDatabaseHas('delivery_addresses', ['id' => $address->id, 'user_id' => $owner->id, 'address_line' => $address->address_line]);
    }
}
