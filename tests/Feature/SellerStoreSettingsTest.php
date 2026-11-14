<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerStoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_store_details_and_logo(): void
    {
        Storage::fake('public');
        [$owner, , $store] = $this->seller();
        $logo = UploadedFile::fake()->createWithContent(
            'store-logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg=='),
        );
        $this->actingAs($owner)->get(route('catalog.store', $store->slug))
            ->assertSee('No description added yet.');

        $response = $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => 'Handmade home goods from Manila.',
            'phone' => '+639171234568',
            'address_line' => '25 New Market Street',
            'city' => 'Quezon City',
            'logo' => $logo,
        ]);

        $store->refresh();
        $response->assertRedirect(route('seller.store-settings.edit'))
            ->assertSessionHas('status', 'Store details saved.');
        $this->assertSame('Handmade home goods from Manila.', $store->description);
        $this->assertSame('+639171234568', $store->phone);
        $this->assertSame('25 New Market Street', $store->address_line);
        $this->assertSame('Quezon City', $store->city);
        Storage::disk('public')->assertExists($store->logo_path);
        $this->actingAs($owner)->get(route('seller.store-settings.edit'))
            ->assertSee("Current {$store->name} logo", false)
            ->assertSee('Handmade home goods from Manila.')
            ->assertSee('+639171234568')
            ->assertSee('25 New Market Street')
            ->assertSee('Quezon City')
            ->assertSee(Storage::disk('public')->url($store->logo_path), false);
        $this->actingAs($owner)->get(route('seller.dashboard'))
            ->assertSee(Storage::disk('public')->url($store->logo_path), false);
        $this->get(route('catalog.store', $store->slug))
            ->assertSee('alt="'.$store->name.' logo"', false)
            ->assertSee('Handmade home goods from Manila.')
            ->assertSee('Quezon City')
            ->assertDontSee('+639171234568')
            ->assertDontSee('25 New Market Street')
            ->assertSee(Storage::disk('public')->url($store->logo_path), false);
        $response->assertSessionMissing('seller_store_settings_undo');
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $owner->id,
            'action' => 'store.updated',
            'entity_id' => $store->id,
        ]);
    }

    public function test_manager_can_edit_shop_settings_but_staff_cannot(): void
    {
        [, $tenant] = $this->seller();
        $manager = User::factory()->seller()->create(['email_verified_at' => now()]);
        $staff = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $manager->id, 'role' => 'manager']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'staff']);

        $this->actingAs($manager)->get(route('seller.store-settings.edit'))->assertOk();
        $this->actingAs($manager)->patch(route('seller.store-settings.update'), [
            'description' => 'Updated by manager.',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
        ])->assertRedirect(route('seller.store-settings.edit'));

        $this->actingAs($staff)->patch(route('seller.store-settings.update'), [
            'description' => 'Unauthorized change.',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
        ])->assertForbidden();
        $this->actingAs($staff)->post(route('seller.store-settings.undo'))->assertForbidden();

        $this->assertDatabaseHas('stores', ['tenant_id' => $tenant->id, 'description' => 'Updated by manager.']);
    }

    public function test_update_rejects_invalid_logo_and_preserves_existing_shop_details(): void
    {
        [$owner, , $store] = $this->seller();

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => 'Should not be saved.',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'logo' => UploadedFile::fake()->create('payload.jpg', 10, 'text/plain'),
        ])->assertSessionHasErrors('logo');

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'description' => null]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_failed_save_shows_one_error_summary(): void
    {
        [$owner] = $this->seller();
        $invalidLogo = fn () => ['logo' => UploadedFile::fake()->create('payload.jpg', 10, 'text/plain')];

        $this->actingAs($owner)->from(route('seller.store-settings.edit'))->followingRedirects()
            ->patch(route('seller.store-settings.update'), $invalidLogo())
            ->assertSee('We could not save your changes')
            ->assertDontSee('data-validation-summary', false);

        // Pages without their own summary still get the layout's.
        $this->actingAs($owner)->from(route('seller.dashboard'))->followingRedirects()
            ->patch(route('seller.store-settings.update'), $invalidLogo())
            ->assertSee('data-validation-summary', false);
    }

    public function test_update_rejects_replacing_and_removing_the_logo_in_the_same_save(): void
    {
        Storage::fake('public');
        [$owner, , $store] = $this->seller();
        $store->update(['logo_path' => 'store-logos/current.png']);
        Storage::disk('public')->put($store->logo_path, 'current logo');

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => 'Updated description.',
            'phone' => $store->phone,
            'address_line' => $store->address_line,
            'city' => $store->city,
            'logo' => UploadedFile::fake()->createWithContent(
                'replacement.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg=='),
            ),
            'remove_logo' => '1',
        ])->assertInvalid(['remove_logo' => 'Choose a replacement logo or remove the current logo, not both.']);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'logo_path' => 'store-logos/current.png', 'description' => null]);
        Storage::disk('public')->assertExists('store-logos/current.png');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owner_can_remove_existing_store_logo(): void
    {
        Storage::fake('public');
        [$owner, , $store] = $this->seller();
        $store->update(['logo_path' => 'store-logos/current.png']);
        Storage::disk('public')->put($store->logo_path, 'logo');

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => null,
            'phone' => $store->phone,
            'address_line' => $store->address_line,
            'city' => $store->city,
            'remove_logo' => '1',
        ])->assertRedirect(route('seller.store-settings.edit'));

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'logo_path' => null]);
        Storage::disk('public')->assertMissing('store-logos/current.png');
        $this->actingAs($owner)->get(route('seller.dashboard'))
            ->assertDontSee(Storage::disk('public')->url('store-logos/current.png'), false);
    }

    public function test_owner_can_undo_a_recent_text_only_settings_save(): void
    {
        [$owner, , $store] = $this->seller();

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => 'Updated description.',
            'phone' => '+639171234568',
            'address_line' => '25 New Market Street',
            'city' => 'Quezon City',
        ])->assertRedirect(route('seller.store-settings.edit'));

        $this->get(route('seller.store-settings.edit'))->assertSee('Undo last save');
        $this->post(route('seller.store-settings.undo'))
            ->assertRedirect(route('seller.store-settings.edit'))
            ->assertSessionHas('status', 'Last store details save undone.')
            ->assertSessionMissing('seller_store_settings_undo');

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'description' => null,
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $owner->id,
            'action' => 'store.update_undone',
            'entity_id' => $store->id,
        ]);
    }

    public function test_undo_does_not_overwrite_store_details_changed_after_the_save(): void
    {
        [$owner, , $store] = $this->seller();

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => 'Updated description.',
            'phone' => '+639171234568',
            'address_line' => '25 New Market Street',
            'city' => 'Quezon City',
        ])->assertRedirect(route('seller.store-settings.edit'));

        $store->update(['description' => 'Changed by another manager.']);

        $this->post(route('seller.store-settings.undo'))
            ->assertRedirectBackWithErrors(['undo']);

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'description' => 'Changed by another manager.',
            'phone' => '+639171234568',
        ]);
    }

    public function test_expired_settings_undo_is_rejected_without_changing_the_store(): void
    {
        [$owner, , $store] = $this->seller();

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => 'Updated description.',
            'phone' => '+639171234568',
            'address_line' => '25 New Market Street',
            'city' => 'Quezon City',
        ])->assertRedirect(route('seller.store-settings.edit'));

        $this->travel(6)->minutes();

        $this->post(route('seller.store-settings.undo'))
            ->assertRedirectBackWithErrors(['undo']);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'description' => 'Updated description.']);
    }

    public function test_store_settings_edit_is_tenant_scoped_and_ignores_unvalidated_store_fields(): void
    {
        [$owner, , $store] = $this->seller();
        [, , $otherStore] = $this->seller();
        $otherStore->update(['phone' => '+639177654321']);

        $this->actingAs($owner)->get(route('seller.store-settings.edit'))
            ->assertOk()
            ->assertSee($store->phone)
            ->assertDontSee($otherStore->phone);

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => 'My store description.',
            'phone' => $store->phone,
            'address_line' => $store->address_line,
            'city' => $store->city,
            'store_id' => $otherStore->id,
            'slug' => 'changed-slug',
            'status' => 'suspended',
        ])->assertRedirect(route('seller.store-settings.edit'));

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'description' => 'My store description.', 'slug' => $store->slug, 'status' => 'active']);
        $this->assertDatabaseHas('stores', ['id' => $otherStore->id, 'description' => null, 'status' => 'active']);
    }

    public function test_logo_storage_failure_returns_a_recoverable_form_error(): void
    {
        [$owner, , $store] = $this->seller();
        Storage::shouldReceive('disk')
            ->once()
            ->with('public')
            ->andThrow(new \RuntimeException('Storage is unavailable.'));

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'description' => 'Should not be saved.',
            'phone' => $store->phone,
            'address_line' => $store->address_line,
            'city' => $store->city,
            'logo' => UploadedFile::fake()->createWithContent(
                'store-logo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg=='),
            ),
        ])->assertSessionHasErrors('logo');

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'description' => null]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owner_can_save_location_details_and_undo_a_later_save(): void
    {
        [$owner, , $store] = $this->seller();
        $details = [
            'description' => null,
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'region' => 'NCR',
            'province' => 'Metro Manila',
            'barangay' => 'San Lorenzo',
            'postal_code' => '1223',
            'pickup_address' => 'Warehouse 2, Chino Roces Ave',
            'return_address' => '10 Market Street, Manila',
            'processing_days' => '3',
            'fulfillment_methods' => 'Courier pickup',
            'delivery_areas' => 'Metro Manila',
            'latitude' => '14.5547123',
            'longitude' => '121.0244456',
            'delivery_radius_km' => '8',
        ];

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), $details)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'pickup_address' => 'Warehouse 2, Chino Roces Ave', 'delivery_radius_km' => 8]);
        $this->assertEqualsWithDelta(14.5547123, $store->fresh()->latitude, 0.0000001);

        // Resending the saved coordinates must still compare equal, or undo is refused.
        $this->patch(route('seller.store-settings.update'), [...$details, 'phone' => '+639179999999'])
            ->assertSessionHasNoErrors();
        $this->post(route('seller.store-settings.undo'))
            ->assertSessionHas('status', 'Last store details save undone.');

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'phone' => '+639171234567', 'province' => 'Metro Manila']);
    }

    public function test_coordinates_must_be_given_together_and_in_range(): void
    {
        [$owner, , $store] = $this->seller();

        $this->actingAs($owner)->patch(route('seller.store-settings.update'), [
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'latitude' => '95',
            'delivery_radius_km' => '0',
        ])->assertSessionHasErrors(['latitude', 'longitude', 'delivery_radius_km']);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'latitude' => null]);
    }

    private function seller(): array
    {
        $owner = User::factory()->seller()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind-'.str()->lower(str()->random(6)), 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods '.str()->random(6), 'slug' => 'northwind-goods-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$owner, $tenant, $store];
    }
}
