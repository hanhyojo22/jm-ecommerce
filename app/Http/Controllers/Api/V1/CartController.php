<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddProductToCart;
use App\Actions\UpdateCartItemQuantity;
use App\Actions\UpdateCartSelection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CartItemResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->payload($request);
    }

    public function store(Request $request, AddProductToCart $addProductToCart): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'ulid', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'variant_id' => ['nullable', 'ulid'],
        ]);

        $addProductToCart->handle(
            $request->user(),
            Product::query()->findOrFail($data['product_id']),
            $data['quantity'],
            $data['variant_id'] ?? null,
        );

        return $this->payload($request)->setStatusCode(201);
    }

    public function update(Request $request, CartItem $cartItem, UpdateCartItemQuantity $updateQuantity): JsonResponse
    {
        $this->authorize('update', $cartItem);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        $updateQuantity->handle($request->user(), $cartItem, $data['quantity']);

        return $this->payload($request);
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorize('delete', $cartItem);

        $cartItem->delete();

        return $this->payload($request);
    }

    public function updateSelection(Request $request, UpdateCartSelection $updateSelection): JsonResponse
    {
        $data = $request->validate([
            'selected' => ['present', 'array'],
            'selected.*' => ['ulid'],
        ]);

        return response()->json(['data' => $updateSelection->handle($request->user(), $data['selected'])]);
    }

    public function destroySelected(Request $request): JsonResponse
    {
        $cart = $request->user()->cart;
        $cart?->items()->where('selected', true)->delete();

        return $this->payload($request);
    }

    /**
     * Return the whole cart after a change so the app does not have to refetch it.
     */
    private function payload(Request $request): JsonResponse
    {
        $cart = $request->user()->cart()->with([
            'items.product:id,tenant_id,store_id,name,slug,price,stock_quantity,status',
            'items.product.store:id,name,slug,status',
            'items.product.tenant:id,status',
            'items.product.thumbnail',
            'items.product.variants',
            'items.product.images' => fn ($query) => $query->whereNotNull('color'),
            'items.variant',
        ])->first();

        $this->deselectUnavailableItems($cart);

        $items = $cart?->items ?? collect();
        $selected = $items->where('selected', true);

        $subtotal = $selected->reduce(
            fn (string $total, CartItem $item): string => bcadd(
                $total,
                bcmul($item->unitPrice(), (string) $item->quantity, 2),
                2,
            ),
            '0.00',
        );

        return response()->json(['data' => [
            'id' => $cart?->id,
            'items' => CartItemResource::collection($items)->resolve($request),
            'selected_count' => $selected->count(),
            'subtotal' => $subtotal,
        ]]);
    }

    /**
     * An item that went out of stock while ticked is unticked, matching the web cart.
     */
    private function deselectUnavailableItems(?Cart $cart): void
    {
        $unavailableIds = $cart?->items
            ->filter(fn (CartItem $item): bool => $item->selected && ! $item->isAvailable())
            ->pluck('id');

        if ($unavailableIds === null || $unavailableIds->isEmpty()) {
            return;
        }

        $cart->items()->whereKey($unavailableIds)->update(['selected' => false]);
        $cart->items->whereIn('id', $unavailableIds)->each->setAttribute('selected', false);
    }
}
