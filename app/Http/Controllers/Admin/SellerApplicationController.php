<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveSellerApplication;
use App\Actions\RejectSellerApplication;
use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class SellerApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SellerApplication::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        return view('admin.seller-applications.index', [
            'applications' => SellerApplication::query()
                ->with('user:id,name,email')
                ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                    $search = '%'.mb_strtolower($search).'%';

                    $query->where(function (Builder $applicationQuery) use ($search): void {
                        $applicationQuery->whereRaw('LOWER(store_name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(seller_name) LIKE ?', [$search])
                            ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                                $userQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                                    ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
                            });
                    });
                })
                ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
                ->latest('submitted_at')
                ->paginate(16)
                ->withQueryString(),
            'filters' => $filters,
            'statusCounts' => SellerApplication::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }

    public function show(SellerApplication $sellerApplication): View
    {
        $this->authorize('view', $sellerApplication);

        return view('admin.seller-applications.show', [
            'application' => $sellerApplication->load('user:id,name,email'),
        ]);
    }

    public function approve(Request $request, SellerApplication $sellerApplication, ApproveSellerApplication $action): RedirectResponse
    {
        $this->authorize('approve', $sellerApplication);
        try {
            $action->handle($request->user(), $sellerApplication, $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['application' => $exception->getMessage()]);
        }

        return to_route('admin.seller-applications.index')->with('status', 'Seller application approved. An active store has been created and now appears in Stores.');
    }

    public function reject(Request $request, SellerApplication $sellerApplication, RejectSellerApplication $action): RedirectResponse
    {
        $this->authorize('approve', $sellerApplication);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $action->handle($request->user(), $sellerApplication, $data['reason'], $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['application' => $exception->getMessage()]);
        }

        return to_route('admin.seller-applications.index')->with('status', 'Seller application rejected with a recorded reason.');
    }
}
