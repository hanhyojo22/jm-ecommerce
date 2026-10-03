<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerEarning;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->user()->sellerTenant();

        abort_unless($tenant !== null, 403);

        $earnings = SellerEarning::query()
            ->where('tenant_id', $tenant->id)
            ->with(['sellerOrder.order', 'payout'])
            ->latest('available_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('seller.earnings.index', [
            'earnings' => $earnings,
            'availableBalance' => SellerEarning::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'available')
                ->sum('net_amount'),
            'paidOutTotal' => SellerEarning::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'paid_out')
                ->sum('net_amount'),
        ]);
    }
}
