<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnCase extends Model
{
    use HasUlids;

    protected $fillable = ['seller_order_id', 'buyer_id', 'opened_by', 'order_issue_id', 'status', 'reason', 'requested_remedy', 'remedy', 'details', 'response_note', 'return_waived', 'return_instructions', 'return_method', 'respond_by', 'buyer_respond_by', 'fulfill_by', 'handed_off_at', 'received_at', 'completed_at'];

    protected function casts(): array
    {
        return ['return_waived' => 'boolean', 'respond_by' => 'datetime', 'buyer_respond_by' => 'datetime', 'fulfill_by' => 'datetime', 'handed_off_at' => 'datetime', 'received_at' => 'datetime', 'completed_at' => 'datetime'];
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
