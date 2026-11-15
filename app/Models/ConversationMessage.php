<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ConversationMessage extends Model
{
    use HasUlids;

    protected $fillable = ['conversation_id', 'sender_id', 'client_message_id', 'type', 'body', 'product_id', 'seller_order_id', 'context_snapshot', 'reply_to_id'];

    protected function casts(): array
    {
        return ['context_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (ConversationMessage $message): void {
            if ($message->product_id !== null && $message->seller_order_id !== null) {
                throw new DomainException('A message may have only one context.');
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(MessageReport::class, 'conversation_message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'conversation_message_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * A short preview of this message for the quote shown above a reply.
     *
     * @return array{id: string, sender_id: string, body: string, context_label: string|null}
     */
    public function quote(): array
    {
        $snapshot = $this->context_snapshot;

        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'body' => Str::limit(filled($this->body) ? $this->body : ($this->type === 'file' ? 'Attachment' : ''), 120),
            'context_label' => match ($snapshot['type'] ?? null) {
                'product' => $snapshot['title'] ?? 'Product',
                'order' => 'Order '.($snapshot['number'] ?? ''),
                default => null,
            },
        ];
    }
}
