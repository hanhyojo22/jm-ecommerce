<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUlids, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Pending review', 'active' => 'Published', 'changes_required' => 'Changes required', 'rejected' => 'Rejected', 'inactive' => 'Suspended'];

    protected $fillable = [
        'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'sku', 'description', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status', 'moderation_reason',
    ];

    /** @return array<string, string> */
    public function moderationOptions(): array
    {
        return match ($this->status) {
            'pending' => ['active' => 'Approve and publish', 'changes_required' => 'Request changes', 'rejected' => 'Reject listing'],
            'active' => ['inactive' => 'Suspend listing'],
            'inactive' => ['active' => 'Restore publication'],
            default => [],
        };
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'compare_at_price' => 'decimal:2', 'free_shipping' => 'boolean'];
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
