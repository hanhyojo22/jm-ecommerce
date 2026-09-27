<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerApplication extends Model
{
    use HasUlids;

    protected $fillable = ['store_name', 'store_description', 'seller_name', 'phone', 'address_line', 'city', 'store_category', 'logo_path', 'status', 'submitted_at', 'tenant_id', 'reviewed_at', 'reviewed_by', 'review_note'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
