<?php

namespace App\Models;

use App\Support\PlatformFee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnCaseOutcome extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['recorded_by', 'kind', 'amount', 'paid_at', 'proof_evidence_id', 'shipment_reference', 'shipped_at', 'created_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'commission_credit' => 'decimal:2', 'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /**
     * Snapshot the platform fee credited back for a refund, so statements and the
     * earnings page sum one stored value and a later rate change never rewrites it.
     */
    protected static function booted(): void
    {
        static::creating(function (ReturnCaseOutcome $outcome): void {
            $earning = $outcome->kind === 'refund' && $outcome->amount !== null
                ? SellerEarning::query()->whereHas('sellerOrder.returnCases', fn ($query) => $query->whereKey($outcome->return_case_id))->first()
                : null;

            // An online refund already gives back JMMart's share of the split, so there is nothing to credit.
            if ($earning === null || $earning->fee_collected_at_payment) {
                return;
            }

            $credit = PlatformFee::on((string) $outcome->amount, (string) $earning->commission_rate);
            $outcome->commission_credit = bccomp($credit, (string) $earning->commission_amount, 2) > 0 ? (string) $earning->commission_amount : $credit;
        });
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }
}
