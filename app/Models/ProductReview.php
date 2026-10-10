<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductReview extends Model
{
    use HasUlids;

    protected $fillable = ['product_id', 'order_item_id', 'user_id', 'rating', 'title', 'body', 'status', 'moderation_reason', 'moderated_by'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductReviewPhoto::class)->orderBy('position');
    }

    public function response(): HasOne
    {
        return $this->hasOne(ProductReviewResponse::class)->whereNull('parent_response_id')->where('author_type', 'seller');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ProductReviewResponse::class)->orderBy('created_at')->orderBy('id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductReviewReport::class);
    }
}
