<?php

namespace App\Models;

use Database\Factories\SellerEarningFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerEarning extends Model
{
    /** @use HasFactory<SellerEarningFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['seller_order_id', 'tenant_id', 'store_id', 'seller_payout_id', 'gross_amount', 'commission_rate', 'commission_amount', 'net_amount', 'status', 'available_at', 'voided_at', 'platform_fee_statement_id', 'fee_collected_at_payment'];

    protected function casts(): array
    {
        return [
            'fee_collected_at_payment' => 'boolean',
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'available_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(SellerPayout::class, 'seller_payout_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function platformFeeStatement(): BelongsTo
    {
        return $this->belongsTo(PlatformFeeStatement::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
