<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryAddressRequest;
use App\Http\Resources\Api\V1\DeliveryAddressResource;
use App\Models\DeliveryAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeliveryAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeliveryAddress::class);

        return DeliveryAddressResource::collection(
            $request->user()->deliveryAddresses()->latest()->get(),
        )->response();
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
