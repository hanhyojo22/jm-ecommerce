<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryAddressRequest;
use App\Http\Resources\Api\V1\DeliveryAddressResource;
use App\Models\DeliveryAddress;
use App\Support\ApiError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DeliveryAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeliveryAddress::class);

        return DeliveryAddressResource::collection(
            $request->user()->deliveryAddresses()->latest()->get(),
        )->response();
    }

    /**
     * Turn the customer's location into address fields for the app's address form: the nearest
     * street, its barangay, city and postal code, from Photon (OpenStreetMap data). Cached per
     * ~11 m cell, since the public Photon service asks for light traffic.
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeliveryAddress::class);

        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $latitude = round((float) $coordinates['latitude'], 4);
        $longitude = round((float) $coordinates['longitude'], 4);

        try {
            $features = Cache::remember("address-lookup:{$latitude},{$longitude}", now()->addDay(), fn (): array => $this->photon('reverse', [
                'lat' => $latitude,
                'lon' => $longitude,
                'layer' => 'street',
                'limit' => 1,
            ]));
        } catch (ConnectionException|RequestException) {
            return ApiError::response('address_lookup_failed', 'We could not look up your address. Type it in instead.', 503);
        }

        if ($features === []) {
            return ApiError::response('address_not_found', 'No street address was found here. Type it in instead.', 422);
        }

        return response()->json(['data' => $this->addressFields($features[0])]);
    }

    /**
     * Address suggestions as the customer types a street, barangay or building, limited to the
     * Philippines. Each one carries the fields it fills in.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeliveryAddress::class);

        $query = trim($request->validate(['q' => ['required', 'string', 'min:3', 'max:100']])['q']);

        try {
            $features = Cache::remember('address-search:'.mb_strtolower($query), now()->addDay(), fn (): array => $this->photon('api', [
                'q' => $query,
                'limit' => 8,
                'bbox' => '116.9,4.5,126.7,21.2',
            ]));
        } catch (ConnectionException|RequestException) {
            return ApiError::response('address_lookup_failed', 'Address suggestions are not available right now.', 503);
        }

        $suggestions = collect($features)
            ->map(fn (array $feature): array => $this->addressFields($feature))
            ->filter(fn (array $fields): bool => $fields['address_line'] !== '')
            ->map(fn (array $fields): array => ['label' => implode(', ', array_filter([$fields['address_line'], $fields['city']])), ...$fields])
            ->unique('label')
            ->values();

        return response()->json(['data' => $suggestions]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function photon(string $endpoint, array $query): array
    {
        return Http::withUserAgent('EverydayPharmacy/1.0 (delivery address lookup)')
            ->timeout(8)
            ->get("https://photon.komoot.io/{$endpoint}", [...$query, 'lang' => 'en'])
            ->throw()
            ->json('features') ?? [];
    }

    /**
     * A Photon place as the address form's fields: building, street and barangay on the
     * address line. OpenStreetMap gives the Philippines regions, not provinces, so province
     * stays empty for the customer to fill in.
     *
     * @param  array<string, mixed>  $feature
     * @return array{address_line: string, city: string, province: string, postal_code: string}
     */
    private function addressFields(array $feature): array
    {
        $place = $feature['properties'] ?? [];
        $kind = $place['osm_key'] ?? '';
        $street = $kind === 'highway' ? ($place['name'] ?? null) : ($place['street'] ?? null);
        $street = $street !== null ? trim(($place['housenumber'] ?? '').' '.$street) : null;
        $building = in_array($kind, ['highway', 'place'], true) ? null : ($place['name'] ?? null);
        $area = $place['district'] ?? $place['locality'] ?? ($kind === 'place' ? ($place['name'] ?? null) : null);
        $postcode = (string) ($place['postcode'] ?? '');

        return [
            'address_line' => implode(', ', array_unique(array_filter([$building, $street, $area]))),
            'city' => (string) ($place['city'] ?? $place['county'] ?? ''),
            'province' => '',
            'postal_code' => preg_match('/^\d{4}$/', $postcode) === 1 ? $postcode : '',
        ];
    }

    public function store(DeliveryAddressRequest $request): JsonResponse
    {
        $address = $request->user()->deliveryAddresses()->create($request->validated());

        return DeliveryAddressResource::make($address)->response()->setStatusCode(201);
    }

    public function update(DeliveryAddressRequest $request, DeliveryAddress $deliveryAddress): JsonResponse
    {
        $this->authorize('update', $deliveryAddress);

        $deliveryAddress->update($request->validated());

        return DeliveryAddressResource::make($deliveryAddress)->response();
    }

    public function destroy(DeliveryAddress $deliveryAddress): Response
    {
        $this->authorize('delete', $deliveryAddress);

        $deliveryAddress->delete();

        return response()->noContent();
    }
}
