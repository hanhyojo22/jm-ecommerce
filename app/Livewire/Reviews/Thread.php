<?php

namespace App\Livewire\Reviews;

use App\Actions\SaveSellerReviewResponse;
use App\Models\AuditLog;
use App\Models\ProductReview;
use App\Models\ProductReviewResponse;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Thread extends Component
{
    public ProductReview $review;

    public bool $sellerView = false;

    public bool $repliesExpanded = true;

    public ?string $replyToId = null;

    public bool $composerOpen = false;

    public string $body = '';

    public bool $saved = false;

    public array $replyBodies = [];

    public ?string $savedReplyId = null;

    public function mount(ProductReview $review, bool $sellerView = false): void
    {
        $this->review = $review;
        $this->sellerView = $sellerView;
        $this->body = $sellerView ? $review->response()->first()?->body ?? '' : '';
        $this->composerOpen = $sellerView;
    }

    public function replyTo(string $responseId): void
    {
        $response = $this->review->responses()->findOrFail($responseId);
        abort_unless($this->canReplyTo($response), 403);

        $this->replyToId = $response->id;
        $this->body = '';
        $this->saved = false;
        $this->composerOpen = true;
    }

    public function cancelReply(): void
    {
        $this->replyToId = null;
        $this->body = $this->sellerView ? $this->review->response()->first()?->body ?? '' : '';
        $this->composerOpen = $this->sellerView;
        $this->resetValidation();
    }

    public function submit(SaveSellerReviewResponse $saveSellerResponse): void
    {
        $user = auth()->user();
        abort_unless($user?->hasVerifiedClerkEmail(), 403);

        $this->body = trim($this->body);
        $this->validate(['body' => ['required', 'string', 'max:2000']]);
        $body = $this->body;

        $this->review->loadMissing('product.store');
        $store = $this->review->product->store;
        $isSeller = $user->account_type === 'seller' && $user->sellerTenant()?->id === $store->tenant_id;
        $isBuyer = $user->account_type === 'buyer' && $user->id === $this->review->user_id;
        abort_unless($isSeller || $isBuyer, 403);
        if ($this->replyToId === null) {
            abort_unless($isSeller, 403);
            $saveSellerResponse->handle($this->review, $user, $body, request()->ip());
        } else {
            abort_unless($this->review->fresh()?->status === 'published', 403);
            $parent = $this->review->responses()->findOrFail($this->replyToId);
            abort_unless(($isBuyer && $parent->author_type === 'seller') || ($isSeller && $parent->author_type === 'buyer'), 403);
            $this->saveNestedReply($parent, $user, $body, $isSeller);
        }

        $this->body = $this->sellerView ? $this->review->response()->first()?->body ?? '' : '';
        $this->replyToId = null;
        $this->composerOpen = $this->sellerView;
        $this->repliesExpanded = true;
        $this->saved = true;
    }

    public function submitCustomerReply(string $responseId): void
    {
        $parent = $this->review->responses()->findOrFail($responseId);
        $user = auth()->user();
        abort_unless($user?->account_type === 'buyer' && $this->canReplyTo($parent), 403);

        if (is_string($this->replyBodies[$responseId] ?? null)) {
            $this->replyBodies[$responseId] = trim($this->replyBodies[$responseId]);
        }

        $this->validate(["replyBodies.{$responseId}" => ['required', 'string', 'max:2000']]);
        $this->saveNestedReply($parent, $user, $this->replyBodies[$responseId], false);
        $this->replyBodies[$responseId] = '';
        $this->savedReplyId = $responseId;
        $this->repliesExpanded = true;
    }

    private function saveNestedReply(ProductReviewResponse $parent, User $user, string $body, bool $isSeller): void
    {
        DB::transaction(function () use ($user, $body, $parent, $isSeller): void {
            $this->review->responses()->create([
                'user_id' => $user->id,
                'parent_response_id' => $parent->id,
                'author_type' => $isSeller ? 'seller' : 'buyer',
                'body' => $body,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $user->id,
                'action' => 'product_review.reply_saved',
                'entity_type' => ProductReview::class,
                'entity_id' => $this->review->id,
                'new_values' => ['parent_response_id' => $parent->id],
                'ip_address' => request()->ip(),
            ]);
        });
    }

    private function canReplyTo(ProductReviewResponse $response): bool
    {
        $user = auth()->user();
        if (! $user?->hasVerifiedClerkEmail() || $this->review->fresh()?->status !== 'published') {
            return false;
        }

        if ($user->account_type === 'buyer') {
            return $user->id === $this->review->user_id && $response->author_type === 'seller';
        }

        $this->review->loadMissing('product.store');

        return $user->account_type === 'seller'
            && $user->sellerTenant()?->id === $this->review->product->store->tenant_id
            && $response->author_type === 'buyer';
    }

    public function render(): View
    {
        $this->review->load(['user:id,name', 'photos', 'product.store', 'orderItem.sellerOrder:id,order_id']);
        $responses = $this->review->responses()->with('user:id,name')->get();
        $rootResponse = $responses->first(fn (ProductReviewResponse $response): bool => $response->parent_response_id === null && $response->author_type === 'seller');
        $children = $responses->groupBy(fn (ProductReviewResponse $response): string => $response->parent_response_id ?? 'root');
        $thread = [];
        $append = function (string $parentId, int $depth) use (&$append, &$thread, $children): void {
            foreach ($children->get($parentId, collect()) as $response) {
                $thread[] = ['response' => $response, 'depth' => min($depth, 2)];
                $append($response->id, $depth + 1);
            }
        };
        $append('root', 1);

        $store = $this->review->product->store;
        $user = auth()->user();
        $actingAsStore = $user?->account_type === 'seller' && $user->sellerTenant()?->id === $store->tenant_id;
        $canCustomerReply = $user?->account_type === 'buyer'
            && $user->id === $this->review->user_id
            && $user->hasVerifiedClerkEmail()
            && $this->review->status === 'published';

        return view('livewire.reviews.thread', compact('thread', 'store', 'actingAsStore', 'rootResponse', 'canCustomerReply'));
    }
}
