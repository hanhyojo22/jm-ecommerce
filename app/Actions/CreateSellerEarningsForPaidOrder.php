<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\SellerEarning;
use App\Models\SellerOrder;

class CreateSellerEarningsForPaidOrder
{
    public function handle(Order $order): void
    {
        $order->sellerOrders()
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(function (SellerOrder $sellerOrder): void {
                SellerEarning::query()->firstOrCreate(
                    ['seller_order_id' => $sellerOrder->id],
                    [
                        'tenant_id' => $sellerOrder->tenant_id,
                        'store_id' => $sellerOrder->store_id,
                        'gross_amount' => $sellerOrder->total,
                        'commission_amount' => '0.00',
                        'net_amount' => $sellerOrder->total,
                        'status' => 'available',
                        'available_at' => now(),
                    ],
                );
            });
    }
}
