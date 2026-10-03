<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\AdminStoreQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'selected' => ['nullable', 'string', 'max:255'],
        ]);

        $stores = AdminStoreQuery::base()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                AdminStoreQuery::applySearch($query, $search);
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->latest();

        $statusCounts = AdminStoreQuery::statusCounts();

        $selectedStore = null;

        if (filled($filters['selected'] ?? null)) {
            $selectedStore = AdminStoreQuery::base()->whereKey($filters['selected'])->first();

            if ($selectedStore === null) {
                return to_route('dashboard', Arr::except($filters, 'selected'))
                    ->with('error', 'That store is no longer available. Choose another store to continue reviewing the marketplace.');
            }
        }

        return view('dashboard', [
            'stores' => $stores->paginate(12)->withQueryString(),
            'filters' => $filters,
            'totalStores' => AdminStoreQuery::total(),
            'statusCounts' => $statusCounts,
            'pendingReviewCount' => AdminStoreQuery::pendingReviewCount(),
            'selectedStore' => $selectedStore,
        ]);
    }
}
