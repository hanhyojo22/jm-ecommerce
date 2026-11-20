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

    /**
     * @param  array<string, string>  $properties
     * @return array<string, mixed>
     */
    private function feature(array $properties): array
    {
        return ['type' => 'Feature', 'properties' => [...$properties, 'state' => 'Central Visayas', 'country' => 'Philippines']];
    }

    public function test_a_pin_is_turned_into_the_nearest_street_and_its_barangay(): void
    {
        Http::fake(['photon.komoot.io/reverse*' => Http::response(['features' => [$this->feature([
            'osm_key' => 'highway',
            'name' => 'Juana Osmeña Extension',
            'district' => 'Camputhaw',
            'city' => 'Cebu City',
            'postcode' => '6000',
        ])]])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/lookup?latitude=10.3176&longitude=123.8931')
            ->assertOk()
            ->assertExactJson(['data' => [
                'address_line' => 'Juana Osmeña Extension, Camputhaw',
                'city' => 'Cebu City',
                'province' => '',
                'postal_code' => '6000',
            ]]);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'layer=street')
            && str_contains($request->url(), 'lat=10.3176')
            && $request->hasHeader('User-Agent'));
    }

    public function test_typing_part_of_an_address_suggests_matching_places(): void
    {
        Http::fake(['photon.komoot.io/api*' => Http::response(['features' => [
            $this->feature(['osm_key' => 'place', 'name' => 'Camputhaw', 'city' => 'Cebu City', 'postcode' => '6000']),
            $this->feature(['osm_key' => 'building', 'name' => 'Winland Tower A', 'street' => 'Juana Osmeña Extension', 'district' => 'Camputhaw', 'city' => 'Cebu City', 'postcode' => '6000']),
            $this->feature(['osm_key' => 'place', 'name' => 'Camputhaw', 'city' => 'Cebu City', 'postcode' => '6000']),
        ]])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/search?q=camput')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['label' => 'Camputhaw, Cebu City', 'address_line' => 'Camputhaw', 'city' => 'Cebu City', 'province' => '', 'postal_code' => '6000'],
                ['label' => 'Winland Tower A, Juana Osmeña Extension, Camputhaw, Cebu City', 'address_line' => 'Winland Tower A, Juana Osmeña Extension, Camputhaw', 'city' => 'Cebu City', 'province' => '', 'postal_code' => '6000'],
            ]]);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'q=camput') && str_contains($request->url(), 'bbox='));
    }

    public function test_a_failed_lookup_asks_the_customer_to_type_the_address(): void
    {
        Http::fake(['photon.komoot.io/*' => Http::response([], 503)]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/lookup?latitude=10.316&longitude=123.8912')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'address_lookup_failed');
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/search?q=camput')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'address_lookup_failed');
    }

    public function test_a_pin_with_no_street_nearby_is_reported(): void
    {
        Http::fake(['photon.komoot.io/*' => Http::response(['features' => []])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/lookup?latitude=10.2&longitude=123.7')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'address_not_found');
    }

    public function test_guests_bad_coordinates_and_short_searches_are_refused(): void
    {
        Http::fake();

        $this->getJson('/api/v1/delivery-addresses/lookup?latitude=10.316&longitude=123.8912')->assertUnauthorized();
        $this->getJson('/api/v1/delivery-addresses/search?q=camput')->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/lookup?latitude=200&longitude=123.8912')
            ->assertUnprocessable();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/delivery-addresses/search?q=ca')
            ->assertUnprocessable();
        Http::assertNothingSent();
    }
}
