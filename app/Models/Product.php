<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUlids, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Pending review', 'active' => 'Published', 'changes_required' => 'Changes required', 'rejected' => 'Rejected', 'inactive' => 'Suspended', 'unlisted' => 'Unlisted'];

    /** Fields buyers read as the listing's claims; editing them on a live listing asks an administrator to re-check it. */
    public const REVIEWED_FIELDS = ['name', 'description', 'category_id', 'brand', 'condition'];

    protected $fillable = [
        'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'sku', 'description', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status', 'moderation_reason', 'content_changed_at',
    ];

    /** @return array<string, string> */
    public function moderationOptions(): array
    {
        return match ($this->status) {
            'pending' => ['active' => 'Approve and publish', 'changes_required' => 'Request changes', 'rejected' => 'Reject listing'],
            'active' => [...($this->content_changed_at ? ['active' => 'Approve edits'] : []), 'inactive' => 'Suspend listing'],
            'unlisted' => [...($this->content_changed_at ? ['unlisted' => 'Approve edits'] : []), 'inactive' => 'Suspend listing'],
            'inactive' => ['active' => 'Restore publication'],
            default => [],
        };
    }

    /**
     * Whether the seller can change this listing. Only a listing waiting on an administrator is locked.
     */
    public function isEditableBySeller(): bool
    {
        return $this->status !== 'pending';
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'compare_at_price' => 'decimal:2', 'free_shipping' => 'boolean', 'content_changed_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('color')->orderBy('size');
    }

    /**
     * Photos of one color, shared by every size of it, from the already loaded images.
     *
     * @return Collection<int, ProductImage>
     */
    public function colorPhotos(?string $color): Collection
    {
        return filled($color) ? $this->images->where('color', $color)->values() : new Collection;
    }

    /**
     * Whether a buyer could pick a variant with no photo: nothing is shared, and some variant's color has no photos.
     */
    public function hasVariantsWithoutPhotos(): bool
    {
        if ($this->images()->whereNull('color')->exists()) {
            return false;
        }

        return $this->variants()->whereNotIn('color', $this->images()->whereNotNull('color')->distinct()->pluck('color'))->exists();
    }

    public function thumbnail(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany('position', 'min');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(ProductDiscussion::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
