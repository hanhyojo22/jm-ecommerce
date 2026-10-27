<?php

namespace App\Actions;

use App\Models\Payment;
use App\Models\SellerEarning;
use App\Models\SellerOrder;
use App\Models\Tenant;
use App\Support\PlatformFee;

class RecordSellerEarning
{
    /**
     * Record what a delivered store order earned and JMMart's share of it.
     *
     * The store keeps the cash from a COD order (and a GCash/Maya payment sent to it), so the earning is voided for payout
     * and its commission is what the store owes JMMart on the next monthly statement.
     * Orders delivered before the store accepted the fee terms are charged nothing.
     */
    public function handle(SellerOrder $sellerOrder): SellerEarning
    {
        $tenant = Tenant::query()->findOrFail($sellerOrder->tenant_id);
        $deliveredAt = $sellerOrder->delivered_at ?? now();
        $rate = $tenant->platformFeeRateAt($deliveredAt);
        $gross = (string) $sellerOrder->total;
        $commission = PlatformFee::on($gross, $rate);

        return SellerEarning::query()->firstOrCreate(['seller_order_id' => $sellerOrder->id], [
            'tenant_id' => $sellerOrder->tenant_id,
            'store_id' => $sellerOrder->store_id,
            'gross_amount' => $gross,
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'net_amount' => bcsub($gross, $commission, 2),
            'status' => 'available',
            'available_at' => $deliveredAt,
            'voided_at' => in_array($sellerOrder->order()->first()?->payment()->value('method'), ['cod', ...Payment::DIRECT_METHODS], true) ? now() : null,
        ]);
    }
}
