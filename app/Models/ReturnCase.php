<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnCase extends Model
{
    use HasUlids;

    protected $fillable = ['seller_order_id', 'buyer_id', 'opened_by', 'order_issue_id', 'status', 'reason', 'requested_remedy', 'remedy', 'details', 'response_note', 'return_waived', 'return_instructions', 'return_method', 'respond_by', 'buyer_respond_by', 'return_by', 'fulfill_by', 'handed_off_at', 'received_at', 'completed_at'];

    protected function casts(): array
    {
        return ['return_waived' => 'boolean', 'respond_by' => 'datetime', 'buyer_respond_by' => 'datetime', 'return_by' => 'datetime', 'fulfill_by' => 'datetime', 'handed_off_at' => 'datetime', 'received_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /**
     * The timestamp the party whose turn it is has to act by, or null when nobody is on the clock.
     */
    /**
     * Cases whose current party has run out of time. Mirrors deadline() in SQL.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where(fn (Builder $overdue): Builder => $overdue
            ->where(fn (Builder $leg): Builder => $leg->where('status', 'awaiting_seller')->where('respond_by', '<', now()))
            ->orWhere(fn (Builder $leg): Builder => $leg->whereIn('status', ['offered', 'rejected', 'awaiting_confirmation'])->where('buyer_respond_by', '<', now()))
            ->orWhere(fn (Builder $leg): Builder => $leg->whereIn('status', ['awaiting_return', 'awaiting_receipt'])->where('return_by', '<', now()))
            ->orWhere(fn (Builder $leg): Builder => $leg->where('status', 'awaiting_fulfillment')->where('fulfill_by', '<', now())));
    }

    /**
     * Soonest deadline first, cases with nobody on the clock last. The SQL twin of deadline().
     */
    public function scopeOrderByDeadline(Builder $query): Builder
    {
        $deadline = "case status
            when 'awaiting_seller' then respond_by
            when 'offered' then buyer_respond_by
            when 'rejected' then buyer_respond_by
            when 'awaiting_confirmation' then buyer_respond_by
            when 'awaiting_return' then return_by
            when 'awaiting_receipt' then return_by
            when 'awaiting_fulfillment' then fulfill_by
        end";

        return $query->orderByRaw("({$deadline}) is null")->orderByRaw("({$deadline}) asc");
    }

    public function deadline(): ?CarbonInterface
    {
        return match ($this->status) {
            'awaiting_seller' => $this->respond_by,
            'offered', 'rejected', 'awaiting_confirmation' => $this->buyer_respond_by,
            'awaiting_return', 'awaiting_receipt' => $this->return_by,
            'awaiting_fulfillment' => $this->fulfill_by,
            default => null,
        };
    }

    public function isOverdue(): bool
    {
        return $this->deadline()?->isPast() ?? false;
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnCaseItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReturnCaseEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ReturnCaseEvidence::class);
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(ReturnCaseOutcome::class);
    }
}
