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
    }

    public function openResponse(): void
    {
        $this->body = $this->review->response()->first()?->body ?? '';
        $this->saved = false;
        $this->composerOpen = true;
    }

    public function cancelReply(): void
    {
        $this->body = $this->sellerView ? $this->review->response()->first()?->body ?? '' : '';
        $this->composerOpen = false;
        $this->resetValidation();
    }

    public function submit(SaveSellerReviewResponse $saveSellerResponse, ?string $body = null): void
    {
        $user = auth()->user();
        abort_unless($user?->hasVerifiedClerkEmail(), 403);

        if ($body !== null) {
            $this->body = $body;
        }

        $this->body = trim($this->body);
        $this->validate(['body' => ['required', 'string', 'max:2000']]);
        $body = $this->body;

        $this->review->loadMissing('product.store');
        $store = $this->review->product->store;
        $isSeller = $user->account_type === 'seller' && $user->sellerTenant()?->id === $store->tenant_id;
        abort_unless($isSeller, 403);
        $saveSellerResponse->handle($this->review, $user, $body, request()->ip());

        $this->body = '';
        $this->composerOpen = false;
        $this->repliesExpanded = true;
        $this->saved = true;
    }

    public function submitNestedReply(string $responseId, ?string $body = null): void
    {
        $parent = $this->review->responses()->findOrFail($responseId);
        $user = auth()->user();
        abort_unless($user !== null && $this->canReplyTo($parent), 403);

        if ($body !== null) {
            $this->replyBodies[$responseId] = $body;
        }

        if (is_string($this->replyBodies[$responseId] ?? null)) {
            $this->replyBodies[$responseId] = trim($this->replyBodies[$responseId]);
        }

        $this->validate(["replyBodies.{$responseId}" => ['required', 'string', 'max:2000']]);
        $this->saveNestedReply($parent, $user, $this->replyBodies[$responseId], $parent->author_type === 'buyer');
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

        $this->review->loadMissing('product.store');

        if ($user->id === $this->review->user_id && ! $user->isMemberOfTenant($this->review->product->tenant_id)) {
            return $user->id === $this->review->user_id && $response->author_type === 'seller';
        }

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
        $canCustomerReply = $user !== null
            && $user->id === $this->review->user_id
            && ! $user->isMemberOfTenant($store->tenant_id)
            && $user->hasVerifiedClerkEmail()
            && $this->review->status === 'published';

        return view('livewire.reviews.thread', compact('thread', 'store', 'actingAsStore', 'rootResponse', 'canCustomerReply'));
    }
}
