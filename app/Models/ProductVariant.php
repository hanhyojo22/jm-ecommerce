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

    protected $fillable = ['product_id', 'color', 'size', 'sku', 'stock_quantity', 'price'];

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

    public function label(): string
    {
        return implode(' / ', array_filter([$this->color, $this->size], fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
