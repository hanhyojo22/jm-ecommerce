<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\SellerEarning;
use App\Models\SellerPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class RecordSellerPayout
{
    /**
     * @param  array<int, string>  $earningIds
     */
    public function handle(User $administrator, array $earningIds, ?string $note = null, ?string $ipAddress = null): SellerPayout
    {
        return DB::transaction(function () use ($administrator, $earningIds, $note, $ipAddress): SellerPayout {
            $earnings = SellerEarning::query()
                ->with('sellerOrder.order.payment')
                ->whereIn('id', array_values(array_unique($earningIds)))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($earnings->count() !== count(array_unique($earningIds)) || $earnings->contains(fn (SellerEarning $earning): bool => $earning->status !== 'available' || $earning->seller_payout_id !== null || $earning->voided_at !== null || $earning->sellerOrder->order->payment?->method === 'cod' || $earning->sellerOrder->order->payment?->isOnline())) {
                throw new LogicException('One or more selected earnings are no longer available for payout.');
            }

            if ($earnings->pluck('tenant_id')->unique()->count() !== 1 || $earnings->pluck('store_id')->unique()->count() !== 1) {
                throw new LogicException('A payout can only include earnings from one seller store.');
            }

            $amount = $earnings->reduce(
                fn (string $total, SellerEarning $earning): string => bcadd($total, $earning->net_amount, 2),
                '0.00',
            );

            if (bccomp($amount, '9999999999.99', 2) > 0) {
                throw new LogicException('The payout amount exceeds the maximum supported amount.');
            }

            $payout = SellerPayout::query()->create([
                'tenant_id' => $earnings->first()->tenant_id,
                'store_id' => $earnings->first()->store_id,
                'recorded_by' => $administrator->id,
                'reference' => 'PO-'.Str::upper((string) Str::ulid()),
                'amount' => $amount,
                'status' => 'recorded',
                'note' => $note,
                'recorded_at' => now(),
            ]);

            $earnings->each(function (SellerEarning $earning) use ($payout): void {
                $earning->update([
                    'seller_payout_id' => $payout->id,
                    'status' => 'paid_out',
                ]);
            });

            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'seller_payout.recorded',
                'entity_type' => SellerPayout::class,
                'entity_id' => $payout->id,
                'new_values' => [
                    'reference' => $payout->reference,
                    'amount' => $amount,
                    'earning_ids' => $earnings->pluck('id')->all(),
                ],
                'ip_address' => $ipAddress,
            ]);

            return $payout;
        });
    }
}
