<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'product_name',
        'sku',
        'variant_label',
        'unit_price',
        'quantity',
        'line_total',
        'discount_amount',
        'image_path',
        'medication_directions',
    ];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'discount_amount' => 'decimal:2', 'medication_directions' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }

    /**
     * The directions a reminder for this purchase starts from: the pharmacist's override from the
     * prescription review, otherwise the product's current label directions. A pack-size variant's
     * own units per pack win, since a box of 30 holds more than a box of 10.
     *
     * @return array<string, mixed>|null
     */
    public function medicationDirections(): ?array
    {
        $directions = $this->medication_directions ?? $this->product?->medicationDirections();
        $variantPackUnits = $this->product_variant_id === null ? null : $this->variant?->pack_units;

        return $directions === null || $variantPackUnits === null ? $directions : [...$directions, 'pack_units' => $variantPackUnits];
    }

    public function medicationPlans(): BelongsToMany
    {
        return $this->belongsToMany(MedicationPlan::class, 'medication_plan_order_items')->withPivot('units');
    }

    public function inventoryAllocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class);
    }
}
