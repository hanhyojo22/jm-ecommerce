<?php

namespace App\Models;

use Database\Factories\DiscountCardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DiscountCard extends Model
{
    /** How many IDs one account may keep: a child may buy for both parents. */
    public const LIMIT = 3;

    /** @use HasFactory<DiscountCardFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['type', 'id_number', 'name', 'last4', 'document_path', 'document_name', 'document_mime'];

    /**
     * The number is the sensitive part; screens only ever show the last four.
     *
     * @var list<string>
     */
    protected $hidden = ['id_number', 'document_path'];

    /**
     * The ID photo goes with the saved ID; orders keep their own copies.
     */
    protected static function booted(): void
    {
        static::deleted(function (DiscountCard $card): void {
            if ($card->document_path !== null) {
                Storage::disk('chat_attachments')->delete($card->document_path);
            }
        });
    }

    protected function casts(): array
    {
        return ['id_number' => 'encrypted', 'name' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The same shape CheckoutCart takes for a typed card, plus the saved ID photo.
     *
     * @return array{type: string, id_number: string, name: string, document?: array{path: string, name: ?string, mime: ?string}}
     */
    public function toCard(): array
    {
        return [
            'type' => $this->type,
            'id_number' => $this->id_number,
            'name' => $this->name,
            ...($this->document_path === null ? [] : ['document' => [
                'path' => $this->document_path,
                'name' => $this->document_name,
                'mime' => $this->document_mime,
            ]]),
        ];
    }

    /**
     * Same type and number, ignoring case and surrounding spaces. Numbers are
     * encrypted, so this has to compare in PHP.
     */
    public function matches(string $type, string $number): bool
    {
        return $this->type === $type
            && mb_strtoupper(trim($this->id_number)) === mb_strtoupper(trim($number));
    }
}
