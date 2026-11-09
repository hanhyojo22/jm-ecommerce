<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasUlids;

    protected $fillable = ['cart_id', 'product_id', 'product_variant_id', 'variant_key', 'quantity', 'selected'];

    /**
     * The cart's updated_at is its last activity, which carts:remind reads.
     * Bulk query updates and deletes skip this, so those call $cart->touch().
     *
     * @var list<string>
     */
    protected $touches = ['cart'];

    protected function casts(): array
    {
        return ['selected' => 'boolean'];
    }

    /**
     * Whether this item can still be bought at its current quantity. Requires
     * the product, its variants and this item's variant to be loaded.
     */
    public function isAvailable(): bool
    {
        $availableStock = $this->variant?->stock_quantity ?? $this->product->stock_quantity;

        return $this->product->status === 'active'
            && $this->product->store->status === 'active'
            && $this->product->tenant->status === 'active'
            && $this->product->stock_quantity >= $this->quantity
            && $availableStock >= $this->quantity
            && ! ($this->product_variant_id !== null && $this->variant === null)
            && ! ($this->product_variant_id === null && $this->product->variants->isNotEmpty());
    }

    public function unitPrice(): string
    {
        return (string) ($this->variant?->price ?? $this->product->price);
    }

    public function imagePath(): ?string
    {
        return $this->product->colorPhotos($this->variant?->color)->first()?->path ?? $this->product->thumbnail?->path;
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
