<?php

namespace App\Models;

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
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function returnCase(): BelongsTo
    {
        return $this->belongsTo(ReturnCase::class);
    }
}
