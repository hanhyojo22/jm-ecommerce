<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerApplication extends Model
{
    use HasUlids;

    protected $fillable = ['store_name', 'store_description', 'seller_name', 'phone', 'address_line', 'city', 'store_category', 'logo_path', 'status', 'submitted_at', 'tenant_id', 'reviewed_at', 'reviewed_by', 'review_note', 'seller_type', 'date_of_birth', 'business_name', 'registration_number', 'bir_details', 'representative_name', 'store_slug', 'banner_path', 'support_contact', 'region', 'province', 'barangay', 'postal_code', 'pickup_address', 'return_address', 'processing_days', 'fulfillment_methods', 'delivery_areas', 'id_type', 'id_number', 'completed_steps', 'correction_steps'];

    protected $hidden = ['id_number'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'date_of_birth' => 'date', 'id_number' => 'encrypted', 'completed_steps' => 'array', 'correction_steps' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function payoutProfile(): HasOne
    {
        return $this->hasOne(PayoutProfile::class);
    }

    public function policyAcceptances(): HasMany
    {
        return $this->hasMany(PolicyAcceptance::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SellerApplicationReview::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(SellerApplicationStatusHistory::class);
    }
}
