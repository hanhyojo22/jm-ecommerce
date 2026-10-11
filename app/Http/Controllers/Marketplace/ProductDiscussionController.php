<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDiscussion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductDiscussionController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $product->loadMissing('store', 'tenant');
        abort_unless($product->status === 'active' && $product->store->status === 'active' && $product->tenant->status === 'active', 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'string', Rule::exists('product_discussions', 'id')
                ->where('product_id', $product->id)->where('status', 'published')],
        ]);

        $parent = isset($data['parent_id']) ? ProductDiscussion::query()->findOrFail($data['parent_id']) : null;

        ProductDiscussion::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'root_id' => $parent?->root_id ?? $parent?->id,
            'author_type' => $request->user()->account_type === 'seller' && $request->user()->isMemberOfTenant($product->tenant_id) ? 'seller' : 'buyer',
            'body' => trim($data['body']),
            'status' => 'published',
        ]);

        return redirect()->to(route('catalog.show', [$product->store->slug, $product->slug]).'#discussions')
            ->with('status', 'Your message was posted.');
    }
}
