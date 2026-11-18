<?php

namespace App\Models;

use Database\Factories\DiscountCardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCard extends Model
{
    /** How many IDs one account may keep: a child may buy for both parents. */
    public const LIMIT = 3;

    /** @use HasFactory<DiscountCardFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['type', 'id_number', 'name', 'last4'];

    /**
     * The number is the sensitive part; screens only ever show the last four.
     *
     * @var list<string>
     */
    protected $hidden = ['id_number'];

    protected function casts(): array
    {
        return ['id_number' => 'encrypted', 'name' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The same shape CheckoutCart takes for a typed card.
     *
     * @return array{type: string, id_number: string, name: string}
     */
    public function toCard(): array
    {
        return [
            'type' => $this->type,
            'id_number' => $this->id_number,
            'name' => $this->name,
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
