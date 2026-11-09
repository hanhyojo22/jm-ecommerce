<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasUlids;

    protected $fillable = ['sender_id', 'audience', 'user_id', 'title', 'body', 'url', 'recipient_count', 'sent_count', 'cancelled_at'];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return $this->cancelled_at !== null || $this->sent_count >= $this->recipient_count;
    }
}
