<?php

namespace App\Models;

use App\Actions\BranchInventory;
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

    private ?int $branchQuantity = null;

    /**
     * Units of this item on hand at the cart's branch; none when no branch is chosen yet.
     */
    public function availableQuantity(): int
    {
        $branch = $this->cart?->branchFor($this->product->tenant_id);

        return $branch === null ? 0 : ($this->branchQuantity ??= BranchInventory::available($branch->id, $this->product_id, $this->product_variant_id));
    }

    /**
     * Whether this item can still be bought at its current quantity from the cart's branch.
     * Requires the cart's store, the product, its variants and this item's variant to be loaded.
     */
    public function isAvailable(): bool
    {
        $branch = $this->cart?->branchFor($this->product->tenant_id);

        return $branch !== null
            && $branch->status === 'active'
            && $branch->tenant_id === $this->product->tenant_id
            && $this->product->status === 'active'
            && $this->product->tenant->status === 'active'
            && $this->availableQuantity() >= $this->quantity
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
