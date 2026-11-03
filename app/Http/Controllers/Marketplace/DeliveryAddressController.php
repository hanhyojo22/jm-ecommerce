<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryAddressRequest;
use App\Models\DeliveryAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryAddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('marketplace.delivery-addresses.index', [
            'addresses' => $request->user()->deliveryAddresses()->latest()->get(),
        ]);
    }

    public function store(DeliveryAddressRequest $request): RedirectResponse
    {
        $request->user()->deliveryAddresses()->create($request->validated());

        return to_route('delivery-addresses.index')->with('status', 'Delivery address saved.');
    }

    public function edit(Request $request, DeliveryAddress $deliveryAddress): View
    {
        $this->authorize('view', $deliveryAddress);

        return view('marketplace.delivery-addresses.edit', ['address' => $deliveryAddress]);
    }

    public function update(DeliveryAddressRequest $request, DeliveryAddress $deliveryAddress): RedirectResponse
    {
        $this->authorize('update', $deliveryAddress);
        $deliveryAddress->update($request->validated());

        return to_route('delivery-addresses.index')->with('status', 'Delivery address updated.');
    }

    public function destroy(Request $request, DeliveryAddress $deliveryAddress): RedirectResponse
    {
        $this->authorize('delete', $deliveryAddress);
        $deliveryAddress->delete();

        return to_route('delivery-addresses.index')->with('status', 'Delivery address removed.');
    }
}
