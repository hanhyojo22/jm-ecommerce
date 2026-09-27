<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\AdminStoreQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'selected' => ['nullable', 'exists:stores,id'],
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
            $selectedStore = AdminStoreQuery::base()->whereKey($filters['selected'])->firstOrFail();
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
