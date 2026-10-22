<?php

namespace App\Models;

use Database\Factories\PlatformFeeStatementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformFeeStatement extends Model
{
    /** @use HasFactory<PlatformFeeStatementFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'tenant_id', 'period_start', 'gross_total', 'commission_total', 'refund_credit', 'credit_carried_in', 'credit_carried_out',
        'amount_due', 'status', 'due_at', 'payment_reference', 'payment_proof_path', 'payment_proof_name', 'payment_submitted_at',
        'review_note', 'paid_at', 'confirmed_by', 'last_reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'gross_total' => 'decimal:2',
            'commission_total' => 'decimal:2',
            'refund_credit' => 'decimal:2',
            'credit_carried_in' => 'decimal:2',
            'credit_carried_out' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'due_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(SellerEarning::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @param  Builder<PlatformFeeStatement>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'unpaid')->where('amount_due', '>', 0)->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && (float) $this->amount_due > 0 && $this->due_at->isPast();
    }
}
