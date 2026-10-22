<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ReturnCaseOutcome;
use App\Models\SellerEarning;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->user()->sellerTenant();

        abort_unless($tenant !== null, 403);

        $deliveredOrders = SellerOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'delivered');

        $deliveredSales = (string) (clone $deliveredOrders)->sum('total');
        $codSales = (string) (clone $deliveredOrders)
            ->whereHas('order.payment', fn ($query) => $query->where('method', 'cod'))
            ->sum('total');
        $codCollected = (string) (clone $deliveredOrders)
            ->whereNotNull('cod_collected_at')
            ->sum('total');
        $commissionTotal = (string) SellerEarning::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('sellerOrder', fn ($query) => $query->where('status', 'delivered'))
            ->sum('commission_amount');

        $refunds = ReturnCaseOutcome::query()
            ->where('kind', 'refund')
            ->whereHas('returnCase.sellerOrder', fn ($query) => $query->where('tenant_id', $tenant->id)->where('status', 'delivered'));

        $deliveredSales = bcsub($deliveredSales, (string) (clone $refunds)->sum('amount'), 2);
        $commissionTotal = bcsub($commissionTotal, (string) $refunds->sum('commission_credit'), 2);

        $earnings = SellerEarning::query()
            ->where('tenant_id', $tenant->id)
            ->with(['sellerOrder.order', 'payout'])
            ->latest('available_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('seller.earnings.index', [
            'earnings' => $earnings,
            'deliveredSales' => $deliveredSales,
            'codCollected' => $codCollected,
            'awaitingCollection' => bccomp($codSales, $codCollected, 2) === 1 ? bcsub($codSales, $codCollected, 2) : '0.00',
            'commissionTotal' => $commissionTotal,
            'netEarnings' => bcsub($deliveredSales, $commissionTotal, 2),
        ]);
    }
}
