<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MarkConversationRead;
use App\Actions\SendConversationAttachment;
use App\Actions\SendConversationMessage;
use App\Events\ConversationInboxChanged;
use App\Events\TypingStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationMessageResource;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Support\ApiError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $filters = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'product', 'order', 'unread'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $user = $request->user();
        $search = trim($filters['search'] ?? '');
        $filter = $filters['filter'] ?? 'all';

        $conversations = Conversation::query()
            ->with([
                'store',
                'product.thumbnail',
                'sellerOrder.order',
                'messages' => fn ($query) => $query->with(['sender:id,name', 'attachments'])->orderByDesc('created_at')->orderByDesc('id')->limit(1),
            ])
            ->withCount(['messages as unread_messages_count' => fn ($query) => Conversation::constrainToUnread($query, $user)])
            ->visibleTo($user)
            // The app is the buyer's surface; a seller account sees the chats it is buying in.
            ->where('buyer_id', $user->id)
            ->when($filter === 'product', fn (Builder $query) => $query->whereNotNull('product_id'))
            ->when($filter === 'order', fn (Builder $query) => $query->whereNotNull('seller_order_id'))
            ->when($filter === 'unread', fn (Builder $query) => $query->unreadFor($user))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->whereHas('store', fn (Builder $store) => $store->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]))
                        ->orWhereHas('product', fn (Builder $product) => $product->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]))
                        ->orWhereHas('sellerOrder.order', fn (Builder $order) => $order->whereRaw("LOWER(number) LIKE ? ESCAPE '!'", [$term]));
                });
            })
            ->orderByDesc('latest_message_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        return ConversationResource::collection($conversations)->response($request);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['store', 'product.thumbnail', 'sellerOrder.order']);

        return response()->json(['data' => [
            ...ConversationResource::make($conversation)->resolve($request),
            'can_send' => $request->user()->can('send', $conversation),
        ]]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'ulid', 'required_without:seller_order_id'],
            'seller_order_id' => ['nullable', 'ulid', 'required_without:product_id'],
        ]);

        $user = $request->user();

        if (($data['product_id'] ?? null) !== null) {
            $product = Product::query()->with('store.tenant')->findOrFail($data['product_id']);
            abort_unless(
                $product->status === 'active'
                    && $product->store->status === 'active'
                    && $product->store->tenant->status === 'active',
                404,
            );
            abort_if($user->isMemberOfTenant($product->tenant_id), 403);

            $conversation = Conversation::query()->createOrFirst(
                ['buyer_id' => $user->id, 'product_id' => $product->id],
                ['store_id' => $product->store_id],
            );
        } else {
            $sellerOrder = SellerOrder::query()->with(['order', 'store'])->findOrFail($data['seller_order_id']);
            abort_unless($sellerOrder->order->user_id === $user->id, 404);
            abort_if($user->isMemberOfTenant($sellerOrder->tenant_id), 403);

            $conversation = Conversation::query()->createOrFirst(
                ['buyer_id' => $user->id, 'seller_order_id' => $sellerOrder->id],
                ['store_id' => $sellerOrder->store_id],
            );
        }

        $created = $conversation->wasRecentlyCreated;

        if ($created) {
            ConversationInboxChanged::dispatch($conversation);
        }

        $conversation->load(['store', 'product.thumbnail', 'sellerOrder.order']);

        return response()->json(
            ['data' => ConversationResource::make($conversation)->resolve($request)],
            $created ? 201 : 200,
        );
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $conversation->load(['store', 'product.thumbnail', 'sellerOrder.order', 'readStates']);

        $messages = $conversation->messages()
            ->with(['sender:id,name', 'attachments'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        // The page is fetched newest first for a stable cursor, then shown oldest first.
        $messages->setCollection($messages->getCollection()->reverse()->values());

        // Compute the read receipt for the last outgoing message so clients can
        // show a Sent / Delivered / Read checkmark beside it.
        $recipientReadState = $conversation->readStates
            ->where('user_id', '!=', $user->id)
            ->sortByDesc('last_read_at')
            ->first();

        $lastOutgoing = $messages->getCollection()->where('sender_id', '!=', $user->id)->last();

        if ($lastOutgoing !== null && $recipientReadState !== null) {
            if ($recipientReadState->last_read_message_id === $lastOutgoing->id) {
                $lastOutgoing->receipt = 'Read';
            } elseif ($recipientReadState->last_delivered_message_id === $lastOutgoing->id) {
                $lastOutgoing->receipt = 'Delivered';
            } else {
                $lastOutgoing->receipt = 'Sent';
            }
        }

        return ConversationMessageResource::collection($messages)
            ->additional(['conversation' => ConversationResource::make($conversation)->resolve($request)])
            ->response($request);
    }

    public function send(Request $request, Conversation $conversation, SendConversationMessage $sendMessage): JsonResponse
    {
        $this->authorize('send', $conversation);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'client_message_id' => ['required', 'uuid'],
        ]);

        $key = 'conversation-message:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 20)) {
            return ApiError::response('rate.limited', 'Please wait a moment before sending another message.', 429);
        }

        $message = $sendMessage->handle(
            $conversation,
            $request->user(),
            trim($data['body']),
            $data['client_message_id'],
        );

        if ($message->wasRecentlyCreated) {
            RateLimiter::hit($key, 60);
        }

        $message->load(['sender:id,name', 'attachments']);

        return ConversationMessageResource::make($message)
            ->response($request)
            ->setStatusCode($message->wasRecentlyCreated ? 201 : 200);
    }

    public function read(Request $request, Conversation $conversation, MarkConversationRead $markRead): JsonResponse
    {
        $this->authorize('view', $conversation);

        $result = $markRead->handle($conversation, $request->user());

        return response()->json(['data' => [
            'last_message_id' => $result['message']?->id,
            'receipts' => $result['receipts'],
        ]]);
    }

    public function typing(Request $request, Conversation $conversation): Response
    {
        $this->authorize('send', $conversation);

        $key = 'conversation-typing:'.$conversation->id.':'.$request->user()->id;

        RateLimiter::attempt($key, 1, function () use ($conversation, $request): void {
            TypingStatusChanged::dispatch($conversation->id, $request->user()->id, true);
        }, 2);

        return response()->noContent();
    }

    public function attach(Request $request, Conversation $conversation, SendConversationAttachment $sendAttachment): JsonResponse
    {
        $this->authorize('send', $conversation);

        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'client_message_id' => ['required', 'uuid'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $message = $sendAttachment->handle(
            $conversation,
            $request->user(),
            $request->file('attachment'),
            trim($data['body'] ?? ''),
            $data['client_message_id'],
        );

        $message->load(['sender:id,name', 'attachments']);

        return ConversationMessageResource::make($message)
            ->response($request)
            ->setStatusCode($message->wasRecentlyCreated ? 201 : 200);
    }
}
