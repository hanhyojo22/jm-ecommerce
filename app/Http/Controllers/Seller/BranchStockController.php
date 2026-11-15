<?php

namespace App\Http\Controllers\Seller;

use App\Actions\SetBranchStock;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchStockController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->branch($request);
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;

        $products = $branch->tenant->products()
            ->with(['variants', 'branchStocks' => fn ($query) => $query->where('store_id', $branch->id)])
            ->when($search, fn ($query, string $term) => $query->where(fn ($match) => $match
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%'])
                ->orWhereRaw('LOWER(generic_name) LIKE ?', ['%'.mb_strtolower($term).'%'])))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('seller.branch-stock.index', [
            'branch' => $branch,
            'branches' => $request->user()->headOfficeTenant()?->stores()->orderBy('name')->get(['id', 'name']),
            'products' => $products,
            'search' => $search,
        ]);
    }

    public function update(Request $request, SetBranchStock $setBranchStock): RedirectResponse
    {
        $branch = $this->branch($request);
        $data = $request->validate([
            'stock' => ['required', 'array', 'max:25'],
            'stock.*' => ['required', 'array', 'max:50'],
            'stock.*.*' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $counts = array_map(fn (array $variants): array => array_map('intval', $variants), $data['stock']);
        $changed = $setBranchStock->handle($request->user(), $branch, $counts, $request->ip());

        return back()->with('status', $changed === 0 ? 'No stock changed.' : "Saved {$changed} stock ".($changed === 1 ? 'count' : 'counts')." for {$branch->name}.");
    }

    /**
     * Branch staff always work on their own branch. Head office picks any of the company's
     * branches; one outside the company is a 404.
     */
    private function branch(Request $request): Store
    {
        $member = $request->user()->sellerMembership() ?? abort(403);

        if (! $member->isHeadOffice()) {
            return $member->store;
        }

        abort_unless(in_array($member->role, ['owner', 'manager'], true), 403);

        return $request->filled('branch')
            ? $member->tenant->stores()->whereKey($request->string('branch')->toString())->firstOrFail()
            : $member->tenant->store;
    }
}
