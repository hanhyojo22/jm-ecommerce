<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDiscussion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductDiscussionController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $request->merge(['body' => is_string($request->input('body')) ? trim($request->input('body')) : $request->input('body')]);
        $product->loadMissing('store', 'tenant');
        abort_unless($product->status === 'active' && $product->store->status === 'active' && $product->tenant->status === 'active', 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'string', Rule::exists('product_discussions', 'id')
                ->where('product_id', $product->id)->where('status', 'published')],
            'seller_reviews' => ['sometimes', 'boolean'],
            'discussion_page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $parent = isset($data['parent_id']) ? ProductDiscussion::query()->findOrFail($data['parent_id']) : null;
        if ($parent?->root_id !== null && ! ProductDiscussion::query()->whereKey($parent->root_id)->where('status', 'published')->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'That discussion is no longer available.']);
        }

        $discussion = ProductDiscussion::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'root_id' => $parent?->root_id ?? $parent?->id,
            'author_type' => $request->user()->account_type === 'seller' && $request->user()->isMemberOfTenant($product->tenant_id) ? 'seller' : 'buyer',
            'body' => trim($data['body']),
            'status' => 'published',
        ]);

        $returnToSellerReviews = ($data['seller_reviews'] ?? false)
            && $parent !== null
            && $request->user()->account_type === 'seller'
            && $request->user()->sellerTenant()?->id === $product->tenant_id;

        if ($returnToSellerReviews && $request->expectsJson()) {
            $discussion->load('user', 'parent.user');

            return response()->json(['html' => view('seller.reviews._discussion-message', [
                'entry' => $discussion,
                'product' => $product,
                'canReply' => true,
                'discussionPage' => $data['discussion_page'] ?? 1,
            ])->render()]);
        }

        return redirect()->to($returnToSellerReviews
            ? route('seller.reviews.index', ['discussion_page' => $data['discussion_page'] ?? 1]).'#discussion-'.($parent?->root_id ?? $parent?->id)
            : route('catalog.show', [$product->store->slug, $product->slug]).'#discussions')
            ->with('status', 'Your message was posted.');
    }
}
