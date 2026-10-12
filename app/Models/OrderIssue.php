<?php

namespace App\Models;

use Database\Factories\OrderIssueFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIssue extends Model
{
    /** @use HasFactory<OrderIssueFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['seller_order_id', 'buyer_id', 'type', 'details', 'status', 'resolution_note', 'resolved_by'];

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
