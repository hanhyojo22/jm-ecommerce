<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductDiscussionResource;
use App\Models\Product;
use App\Queries\CatalogQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductDiscussionController extends Controller
{
    public function index(string $store, string $product): JsonResponse
    {
        $product = $this->publicProduct($store, $product);
        $discussions = $product->discussions()
            ->where('status', 'published')
            ->whereNull('parent_id')
            ->with(['user:id,name', 'product.store', 'replies.user:id,name', 'replies.product.store'])
            ->latest()
            ->paginate(10);

        return ProductDiscussionResource::collection($discussions)->response();
    }

    public function store(Request $request, string $store, string $product): JsonResponse
    {
        $product = $this->publicProduct($store, $product);
        $request->merge(['body' => is_string($request->input('body')) ? trim($request->input('body')) : $request->input('body')]);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'string', Rule::exists('product_discussions', 'id')
                ->where('product_id', $product->id)->where('status', 'published')],
        ]);
        $parent = isset($data['parent_id']) ? $product->discussions()->findOrFail($data['parent_id']) : null;
        if ($parent?->root_id !== null && ! $product->discussions()->whereKey($parent->root_id)->where('status', 'published')->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'That discussion is no longer available.']);
        }

        $user = $request->user();
        $discussion = $product->discussions()->create([
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'root_id' => $parent?->root_id ?? $parent?->id,
            'author_type' => $user->account_type === 'seller' && $user->isMemberOfTenant($product->tenant_id) ? 'seller' : 'buyer',
            'body' => $data['body'],
            'status' => 'published',
        ]);
        $discussion->load('user:id,name', 'product.store');

        return ProductDiscussionResource::make($discussion)->response()->setStatusCode(201);
    }

    private function publicProduct(string $store, string $product): Product
    {
        return CatalogQuery::publishedProducts()
            ->whereHas('store', fn (Builder $query) => $query->where('slug', $store))
            ->where('slug', $product)
            ->firstOrFail();
    }
}
