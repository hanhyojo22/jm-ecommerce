<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Conversation extends Model
{
    use HasUlids;

    protected $fillable = ['buyer_id', 'store_id', 'latest_message_at'];

    protected function casts(): array
    {
        return ['latest_message_at' => 'datetime'];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        $id = DB::table('conversation_aliases')->where('old_id', $value)->value('conversation_id') ?? $value;

        return parent::resolveRouteBinding($id, $field);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
        return $this->blocks()->exists();
    }

    /**
     * Blocks between the buyer and this store's members that the given user created.
     */
    public function blocksCreatedBy(User $user): Builder
    {
        return $this->blocks()->where('user_id', $user->id);
    }

    /**
     * Every block standing between the buyer and this store's members.
     */
    private function blocks(): Builder
    {
        $sellerIds = $this->storeMemberIds();

        return BlockedUser::query()->where(function (Builder $query) use ($sellerIds): void {
            $query->where(fn (Builder $query) => $query->where('user_id', $this->buyer_id)->whereIn('blocked_user_id', $sellerIds))
                ->orWhere(fn (Builder $query) => $query->where('blocked_user_id', $this->buyer_id)->whereIn('user_id', $sellerIds));
        });
    }

    public function storeMemberIds(): Builder
    {
        $this->loadMissing('store');

        return TenantMember::query()
            ->where('tenant_id', $this->store->tenant_id)
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->select('user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('buyer_id', $user->id);

            if ($user->account_type === 'seller') {
                $query->orWhereIn('store_id', Store::query()
                    ->where('status', 'active')
                    ->whereIn('tenant_id', $user->tenantMemberships()
                        ->whereIn('role', ['owner', 'manager', 'staff'])
                        ->whereHas('tenant', fn (Builder $tenant) => $tenant->where('status', 'active'))
                        ->select('tenant_id'))
                    ->select('id'));
            }
        });
    }

    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->whereHas('messages', fn (Builder $messages) => self::constrainToUnread($messages, $user));
    }

    /**
     * Limit a conversation's messages to those the given user has not read yet.
     *
     * @param  Builder<ConversationMessage>  $messages
     * @return Builder<ConversationMessage>
     */
    public static function constrainToUnread(Builder $messages, User $user): Builder
    {
        return $messages
            ->where('sender_id', '!=', $user->id)
            ->whereNotExists(function ($query) use ($user): void {
                $query->selectRaw('1')->from('conversation_message_reads')
                    ->whereColumn('conversation_message_reads.message_id', 'conversation_messages.id')
                    ->where('conversation_message_reads.user_id', $user->id);
            });
    }
}
