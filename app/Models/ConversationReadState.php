<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationReadState extends Model
{
    use HasUlids;

    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id', 'last_read_at', 'last_delivered_message_id', 'last_delivered_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime', 'last_delivered_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'last_read_message_id');
    }
}
