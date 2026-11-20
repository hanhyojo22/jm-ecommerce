<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryAddressLookupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pin_is_turned_into_address_fields(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => [
            'house_number' => '12',
            'road' => 'Osmeña Boulevard',
            'quarter' => 'Capitol Site',
            'city' => 'Cebu City',
            'province' => 'Cebu',
            'state' => 'Central Visayas',
            'postcode' => '6000',
        ]])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/lookup?latitude=10.316&longitude=123.8912')
            ->assertOk()
            ->assertExactJson(['data' => [
                'address_line' => '12 Osmeña Boulevard, Capitol Site',
                'city' => 'Cebu City',
                'province' => 'Cebu',
                'postal_code' => '6000',
            ]]);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'lat=10.316') && $request->hasHeader('User-Agent'));
    }

    public function test_a_failed_lookup_asks_the_customer_to_type_the_address(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 503)]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/lookup?latitude=10.316&longitude=123.8912')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'address_lookup_failed');
    }

    public function test_a_pin_with_no_address_is_reported(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['error' => 'Unable to geocode'])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/lookup?latitude=10.2&longitude=123.7')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'address_not_found');
    }

    public function test_guests_and_bad_coordinates_are_refused(): void
    {
        Http::fake();

        $this->getJson('/api/v1/delivery-addresses/lookup?latitude=10.316&longitude=123.8912')->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/lookup?latitude=200&longitude=123.8912')
            ->assertUnprocessable();
        Http::assertNothingSent();
    }
}
