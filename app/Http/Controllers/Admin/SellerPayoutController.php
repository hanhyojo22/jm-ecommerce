<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordSellerPayout;
use App\Http\Controllers\Controller;
use App\Models\SellerEarning;
use App\Models\SellerPayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class SellerPayoutController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        return view('admin.payouts.index', [
            'availableEarnings' => SellerEarning::query()
                ->where('status', 'available')->whereNull('voided_at')->whereHas('sellerOrder.order.payment', fn ($query) => $query->where('method', '!=', 'cod'))
                ->with(['store', 'sellerOrder.order'])
                ->latest('available_at')
                ->orderByDesc('id')
                ->paginate(25),
            'availableTotal' => SellerEarning::query()->where('status', 'available')->whereNull('voided_at')->whereHas('sellerOrder.order.payment', fn ($query) => $query->where('method', '!=', 'cod'))->sum('net_amount'),
            'recentPayouts' => SellerPayout::query()
                ->with(['store', 'recordedBy'])
                ->latest('recorded_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request, RecordSellerPayout $action): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        $data = $request->validate([
            'earning_ids' => ['required', 'array', 'min:1'],
            'earning_ids.*' => ['required', 'string', 'distinct', 'exists:seller_earnings,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $payout = $action->handle($request->user(), $data['earning_ids'], $data['note'] ?? null, $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['payout' => $exception->getMessage()]);
        }

        return to_route('admin.payouts.index')->with('status', "Payout {$payout->reference} recorded.");
    }
}
