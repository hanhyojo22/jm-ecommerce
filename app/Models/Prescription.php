<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The customer's prescription for an order with a prescription medicine: pending until a pharmacist
 * at the filling branch approves it (the order can then move on) or rejects it (the order is cancelled).
 * The file sits on the private chat_attachments disk.
 */
class Prescription extends Model
{
    use HasUlids;

    protected $fillable = ['order_id', 'user_id', 'path', 'original_name', 'status', 'reviewed_by', 'reviewed_at', 'reason'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
