<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The customer's prescription for an order with a prescription medicine: pending until a pharmacist
 * at the filling branch approves it (the order can then move on) or rejects it (the order is cancelled).
 * The pages sit on the private chat_attachments disk.
 */
class Prescription extends Model
{
    use HasUlids;

    protected $fillable = ['order_id', 'user_id', 'status', 'reviewed_by', 'reviewed_at', 'reason'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<PrescriptionImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(PrescriptionImage::class)->orderBy('position');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The review history, oldest first. Every change to a prescription writes an audit log, so the
     * log is the timeline rather than a second table that could drift from it.
     *
     * @return Collection<int, AuditLog>
     */
    public function timeline(): Collection
    {
        return AuditLog::query()
            ->with('actor:id,name')
            ->where('entity_type', self::class)
            ->where('entity_id', $this->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
