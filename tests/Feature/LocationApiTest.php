<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocationApiTest extends TestCase
{
    public function test_provinces_list_their_cities_with_ncr_under_metro_manila(): void
    {
        $response = $this->getJson(route('api.v1.locations.index'))->assertOk();

        $provinces = collect($response->json('data'))->pluck('cities', 'name');
        $this->assertCount(83, $provinces);
        $this->assertContains('Manila', $provinces['Metro Manila']);
        $this->assertContains('Quezon City', $provinces['Metro Manila']);
        $this->assertContains('Cebu City', $provinces['Cebu']);
        $this->assertContains('Mandaue City', $provinces['Cebu']);
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_barangays_match_the_city_however_it_is_written(): void
    {
        foreach (['Cebu City', 'City of Cebu', 'cebu'] as $city) {
            $this->getJson(route('api.v1.locations.barangays', ['province' => 'CEBU', 'city' => $city]))
                ->assertOk()
                ->assertJsonFragment(['Apas']);
        }
    }

    public function test_an_unknown_place_has_no_barangays_and_both_names_are_required(): void
    {
        $this->getJson(route('api.v1.locations.barangays', ['province' => 'Cebu', 'city' => 'Atlantis']))
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->getJson(route('api.v1.locations.barangays', ['province' => 'Cebu']))
            ->assertUnprocessable();
    }
}
