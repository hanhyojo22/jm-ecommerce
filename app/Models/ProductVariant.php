<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasUlids;

    protected $fillable = ['product_id', 'color', 'color_hex', 'size', 'sku', 'stock_quantity', 'price'];

    /**
     * Swatches guessed from common color names until the seller picks one.
     *
     * @var array<string, string>
     */
    public const SWATCHES = [
        'black' => '#292d34', 'white' => '#e8e4dc', 'gray' => '#8a919c', 'grey' => '#8a919c', 'silver' => '#c4c8ce',
        'navy' => '#263b60', 'blue' => '#4d7fc4', 'red' => '#b74e57', 'green' => '#62856b', 'pink' => '#ddb2bf',
        'yellow' => '#e5c24f', 'orange' => '#e08a3c', 'purple' => '#7b5ea7', 'brown' => '#7a5640', 'beige' => '#d9c8a9',
        'gold' => '#c9a645', 'cream' => '#efe6d2', 'maroon' => '#6e2a33',
    ];

    protected $attributes = ['color' => '', 'size' => ''];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * A variant may use only one of the two options; the unused one is stored as an empty string.
     */
    protected function color(): Attribute
    {
        return Attribute::set(fn (?string $value): string => trim((string) $value));
    }

    protected function size(): Attribute
    {
        return Attribute::set(fn (?string $value): string => trim((string) $value));
    }

    /**
     * The swatch shown for this variant's color: the seller's pick, else a guess from the name.
     */
    public function swatch(): string
    {
        return $this->color_hex ?? self::SWATCHES[strtolower(last(explode(' ', $this->color)))] ?? '#97a0af';
    }

    public function label(): string
    {
        return implode(' / ', array_filter([$this->color, $this->size], fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
