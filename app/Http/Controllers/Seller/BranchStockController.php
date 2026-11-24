<?php

namespace App\Http\Controllers\Seller;

use App\Actions\AdjustBranchStock;
use App\Actions\ReceiveBranchStock;
use App\Actions\SetBranchStock;
use App\Http\Controllers\Controller;
use App\Models\BranchStock;
use App\Models\InventoryBatch;
use App\Models\Product;
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
        $today = today()->toDateString();
        $batchBase = InventoryBatch::query()->where('store_id', $branch->id);
        $sellableBySku = (clone $batchBase)->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today))
            ->selectRaw('product_id, variant_key, SUM(remaining_quantity) as quantity')
            ->groupBy('product_id', 'variant_key')->get()
            ->mapWithKeys(fn (InventoryBatch $batch): array => [$batch->product_id.'|'.$batch->variant_key => (int) $batch->quantity]);
        $stockRows = BranchStock::query()->where('store_id', $branch->id)->get();
        foreach ($stockRows as $stock) {
            $key = $stock->product_id.'|'.$stock->variant_key;
            if (! $sellableBySku->has($key) && ! (clone $batchBase)->where('product_id', $stock->product_id)->where('variant_key', $stock->variant_key)->exists()) {
                $sellableBySku[$key] = $stock->quantity;
            }
        }
        $products->getCollection()->each(function (Product $product) use ($sellableBySku): void {
            $counts = [];
            foreach ($product->variants->isEmpty() ? ['default'] : $product->variants->pluck('id') as $variantKey) {
                $counts[$variantKey] = (int) ($sellableBySku[$product->id.'|'.$variantKey] ?? 0);
            }
            $product->setAttribute('sellable_branch_counts', $counts);
        });
        $watch = [
            'expiring' => (int) (clone $batchBase)->where('status', 'active')->whereBetween('expires_at', [$today, today()->addDays(90)->toDateString()])->sum('remaining_quantity'),
            'expired' => (int) (clone $batchBase)->where('status', 'active')->whereDate('expires_at', '<', $today)->sum('remaining_quantity'),
            'quarantined' => (int) (clone $batchBase)->where('status', 'quarantined')->sum('remaining_quantity'),
            'low_stock' => $sellableBySku->filter(fn (int $quantity): bool => $quantity <= 5)->count(),
            'discrepancies' => $stockRows->filter(fn (BranchStock $stock): bool => (int) $stock->quantity !== (int) ($sellableBySku[$stock->product_id.'|'.$stock->variant_key] ?? 0))->count(),
        ];

        return view('seller.branch-stock.index', [
            'branch' => $branch,
            'branches' => $request->user()->headOfficeTenant()?->stores()->orderBy('name')->get(['id', 'name']),
            'products' => $products,
            'batches' => InventoryBatch::query()->with('product:id,name')->where('store_id', $branch->id)->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('lot_number')->paginate(25, ['*'], 'batches_page')->withQueryString(),
            'watch' => $watch,
            'search' => $search,
        ]);
    }

    public function receive(Request $request, ReceiveBranchStock $receive): RedirectResponse
    {
        $branch = $this->branch($request);
        $data = $request->validate([
            'product_id' => ['required', 'ulid'], 'variant_id' => ['nullable', 'ulid'],
            'lot_number' => ['required', 'string', 'max:100'], 'expires_at' => ['required', 'date', 'after:today'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'supplier' => ['nullable', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $product = Product::query()->where('tenant_id', $branch->tenant_id)->findOrFail($data['product_id']);
        $receive->handle($request->user(), $branch, $product, $data['variant_id'] ?? null, $data, $request->ip());

        return back()->with('status', 'Stock batch received.');
    }

    public function adjust(Request $request, AdjustBranchStock $adjust): RedirectResponse
    {
        $branch = $this->branch($request);
        $data = $request->validate([
            'batch_id' => ['required', 'ulid'], 'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'in:count,damage,expiry,correction,quarantine'],
        ]);
        $batch = InventoryBatch::query()->where('store_id', $branch->id)->whereKey($data['batch_id'])->firstOrFail();
        $adjust->handle($request->user(), $branch, $batch->product, $batch->id, $data['quantity'], $data['reason'], $request->ip());

        return back()->with('status', 'Batch adjustment recorded.');
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
