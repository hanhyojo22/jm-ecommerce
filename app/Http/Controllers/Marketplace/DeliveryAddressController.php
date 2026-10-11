<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
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

    public function store(Request $request): RedirectResponse
    {
        $request->user()->deliveryAddresses()->create($this->validatedAddress($request));

        return to_route('delivery-addresses.index')->with('status', 'Delivery address saved.');
    }

    public function edit(Request $request, DeliveryAddress $deliveryAddress): View
    {
        abort_unless($deliveryAddress->user_id === $request->user()->id, 404);

        return view('marketplace.delivery-addresses.edit', ['address' => $deliveryAddress]);
    }

    public function update(Request $request, DeliveryAddress $deliveryAddress): RedirectResponse
    {
        abort_unless($deliveryAddress->user_id === $request->user()->id, 404);
        $deliveryAddress->update($this->validatedAddress($request));

        return to_route('delivery-addresses.index')->with('status', 'Delivery address updated.');
    }

    public function destroy(Request $request, DeliveryAddress $deliveryAddress): RedirectResponse
    {
        abort_unless($deliveryAddress->user_id === $request->user()->id, 404);
        $deliveryAddress->delete();

        return to_route('delivery-addresses.index')->with('status', 'Delivery address removed.');
    }

    /** @return array{recipient_name: string, phone: string, address_line: string, city: string} */
    private function validatedAddress(Request $request): array
    {
        return $request->validate([
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
        ]);
    }
}
