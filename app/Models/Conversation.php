<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasUlids;

    protected $fillable = ['buyer_id', 'store_id', 'product_id', 'seller_order_id', 'latest_message_at'];

    protected function casts(): array
    {
        return ['latest_message_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Conversation $conversation): void {
            $hasProduct = $conversation->product_id !== null;
            $hasSellerOrder = $conversation->seller_order_id !== null;

            if ($hasProduct === $hasSellerOrder) {
                throw new DomainException('A conversation must have exactly one context.');
            }

            if ($hasProduct && ! Product::query()->whereKey($conversation->product_id)->where('store_id', $conversation->store_id)->exists()) {
                throw new DomainException('The product does not belong to the conversation store.');
            }

            if ($hasSellerOrder && ! SellerOrder::query()->whereKey($conversation->seller_order_id)->where('store_id', $conversation->store_id)->exists()) {
                throw new DomainException('The seller order does not belong to the conversation store.');
            }
        });
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function readStates(): HasMany
    {
        return $this->hasMany(ConversationReadState::class);
    }

    public function isBlocked(): bool
    {
        $sellerIds = TenantMember::query()
            ->where('tenant_id', $this->store()->value('tenant_id'))
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->select('user_id');

        return BlockedUser::query()->where(function (Builder $query) use ($sellerIds): void {
            $query->where(fn (Builder $query) => $query->where('user_id', $this->buyer_id)->whereIn('blocked_user_id', $sellerIds))
                ->orWhere(fn (Builder $query) => $query->where('blocked_user_id', $this->buyer_id)->whereIn('user_id', $sellerIds));
        })->exists();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->account_type === 'buyer') {
            return $query->where('buyer_id', $user->id);
        }

        return $query->whereIn('store_id', Store::query()
            ->where('status', 'active')
            ->whereIn('tenant_id', $user->tenantMemberships()
                ->whereIn('role', ['owner', 'manager', 'staff'])
                ->whereHas('tenant', fn (Builder $tenant) => $tenant->where('status', 'active'))
                ->select('tenant_id'))
            ->select('id'));
    }

    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where(function (Builder $query) use ($user): void {
                $query->whereDoesntHave('readStates', fn (Builder $readStates) => $readStates->where('user_id', $user->id))
                    ->whereHas('messages', fn (Builder $messages) => $messages->where('sender_id', '!=', $user->id));
            })->orWhereHas('readStates', fn (Builder $readStates) => $readStates
                ->where('user_id', $user->id)
                ->whereColumn('last_read_at', '<', 'conversations.latest_message_at'));
        });
    }
}
