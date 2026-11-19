<?php

namespace App\Models;

use Carbon\CarbonInterface;
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

    /** PWD IDs expire, so a pharmacy's review of one is trusted for this long; a senior ID's until its photo changes. */
    public const PWD_REVIEW_MONTHS = 12;

    /** @use HasFactory<DiscountCardFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['type', 'id_number', 'name', 'last4', 'document_path', 'document_name', 'document_mime', 'document_reviewed_at', 'document_reviewed_by', 'document_reviewed_tenant_id'];

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
        return ['id_number' => 'encrypted', 'name' => 'encrypted', 'document_reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The same shape CheckoutCart takes for a typed card, plus the saved ID photo and, while it
     * still counts, the pharmacy's earlier review of that photo.
     *
     * @return array{type: string, id_number: string, name: string, document?: array{path: string, name: ?string, mime: ?string, review: ?array{at: CarbonInterface, by: ?string, tenant_id: string}}}
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
                'review' => $this->standingReview(),
            ]]),
        ];
    }

    /**
     * @return ?array{at: CarbonInterface, by: ?string, tenant_id: string}
     */
    private function standingReview(): ?array
    {
        if ($this->document_reviewed_at === null || $this->document_reviewed_tenant_id === null) {
            return null;
        }

        if ($this->type === 'pwd' && $this->document_reviewed_at->lt(now()->subMonths(self::PWD_REVIEW_MONTHS))) {
            return null;
        }

        return ['at' => $this->document_reviewed_at, 'by' => $this->document_reviewed_by, 'tenant_id' => $this->document_reviewed_tenant_id];
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
