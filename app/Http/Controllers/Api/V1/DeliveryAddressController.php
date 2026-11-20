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
     * Turn the customer's location into address fields for the app's address form, using
     * OpenStreetMap's Nominatim. Lookups are cached per ~11 m cell, since its usage policy asks
     * for light, cached traffic.
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
            $place = Cache::remember("address-lookup:{$latitude},{$longitude}", now()->addDay(), fn (): ?array => Http::withUserAgent('EverydayPharmacy/1.0 (delivery address lookup)')
                ->timeout(8)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 18,
                    'addressdetails' => 1,
                    'accept-language' => 'en',
                ])
                ->throw()
                ->json('address'));
        } catch (ConnectionException|RequestException) {
            return ApiError::response('address_lookup_failed', 'We could not look up your address. Type it in instead.', 503);
        }

        if (! is_array($place)) {
            return ApiError::response('address_not_found', 'No street address was found here. Type it in instead.', 422);
        }

        $street = trim(($place['house_number'] ?? '').' '.($place['road'] ?? ''));
        $area = $place['quarter'] ?? $place['suburb'] ?? $place['neighbourhood'] ?? $place['village'] ?? null;
        $postcode = (string) ($place['postcode'] ?? '');

        return response()->json(['data' => [
            'address_line' => implode(', ', array_filter([$street, $area])),
            'city' => (string) ($place['city'] ?? $place['town'] ?? $place['municipality'] ?? ''),
            'province' => (string) ($place['province'] ?? $place['state'] ?? ''),
            'postal_code' => preg_match('/^\d{4}$/', $postcode) === 1 ? $postcode : '',
        ]]);
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
