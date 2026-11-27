<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One medicine a buyer (or one of their care profiles) takes. units_anchor is the amount on hand
 * at anchored_at; MedicationSupply works out what is left from there.
 */
class MedicationPlan extends Model
{
    use HasUlids;

    public const KINDS = ['maintenance', 'course', 'as_needed'];

    protected $fillable = [
        'care_profile_id', 'product_id', 'product_variant_id', 'product_name', 'medicine_kind', 'dose_quantity', 'dose_unit', 'dose_times',
        'min_hours_between', 'max_doses_per_day', 'directions_note', 'starts_on', 'ends_on', 'units_anchor', 'anchored_at', 'expires_on',
        'show_name_on_lock_screen', 'status', 'last_count_prompt_at',
    ];

    protected function casts(): array
    {
        return [
            'dose_quantity' => 'decimal:2',
            'units_anchor' => 'decimal:2',
            'dose_times' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'expires_on' => 'date',
            'anchored_at' => 'datetime',
            'last_count_prompt_at' => 'datetime',
            'show_name_on_lock_screen' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function careProfile(): BelongsTo
    {
        return $this->belongsTo(CareProfile::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<MedicationDoseLog, $this>
     */
    public function doseLogs(): HasMany
    {
        return $this->hasMany(MedicationDoseLog::class);
    }

    /**
     * The delivered order items whose units went into this plan; each item counts once.
     */
    public function orderItems(): BelongsToMany
    {
        return $this->belongsToMany(OrderItem::class, 'medication_plan_order_items')->withPivot('units');
    }
}
