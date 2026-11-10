<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RateStore;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StoreRatingResource;
use App\Models\SellerOrder;
use App\Queries\CatalogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreRatingController extends Controller
{
    /**
     * The public list of a store's ratings, newest first.
     */
    public function index(Request $request, string $store): JsonResponse
    {
        $store = CatalogQuery::publicStores()->where('slug', $store)->firstOrFail();
        $perPage = (int) ($request->validate(['per_page' => ['nullable', 'integer', 'between:1,50']])['per_page'] ?? 10);

        return StoreRatingResource::collection(
            $store->ratings()->with('user:id,name')->latest()->latest('id')->paginate($perPage)->withQueryString(),
        )->response();
    }

    /**
     * The buyer rates the store for one delivered store order.
     */
    public function store(Request $request, SellerOrder $sellerOrder, RateStore $rateStore): JsonResponse
    {
        $this->authorize('receive', $sellerOrder);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $rating = $rateStore->handle($request->user(), $sellerOrder, $data);

        return StoreRatingResource::make($rating->load('user:id,name'))->response($request)->setStatusCode(201);
    }
}
