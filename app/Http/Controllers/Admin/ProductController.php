<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'status' => ['nullable', 'in:active,draft,inactive,unlisted,pending,changes_required,rejected'],
            'edited' => ['nullable', 'boolean'],
            'added' => ['nullable', 'in:last_30_days,last_90_days,this_year'],
            'sort' => ['nullable', 'in:newest,oldest,recently_updated,name'],
        ]);

        $products = Product::query()
            ->with(['images', 'store:id,name', 'category:id,name'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $productQuery) use ($search): void {
                    $productQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$search])
                        ->orWhereHas('store', fn (Builder $storeQuery): Builder => $storeQuery->whereRaw('LOWER(name) LIKE ?', [$search]));
                });
            })
            ->when($filters['store_id'] ?? null, fn (Builder $query, string $storeId): Builder => $query->where('store_id', $storeId))
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $categoryId): Builder => $query->where('category_id', $categoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['edited'] ?? false, fn (Builder $query): Builder => $query->whereNotNull('content_changed_at'))
            ->when($filters['added'] ?? null, function (Builder $query, string $added): void {
                match ($added) {
                    'last_30_days' => $query->where('created_at', '>=', now()->subDays(30)),
                    'last_90_days' => $query->where('created_at', '>=', now()->subDays(90)),
                    'this_year' => $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
                };
            });

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $products->oldest(),
            'recently_updated' => $products->latest('updated_at'),
            'name' => $products->orderBy('name')->orderBy('id'),
            default => $products->latest(),
        };

        return view('admin.products.index', [
            'products' => $products->paginate(20)->withQueryString(),
            'filters' => $filters,
            'totalProducts' => Product::query()->count(),
            'editedCount' => Product::query()->whereNotNull('content_changed_at')->count(),
            'statusCounts' => Product::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Product $product): View
    {
        $this->authorizeAdmin($request);

        $product = Product::query()
            ->with(['images', 'variants', 'store.tenant', 'category'])
            ->whereKey($product)
            ->firstOrFail();

        return view('admin.products.show', [
            'product' => $product,
            'auditLogs' => AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $product->id)
                ->with('actor')
                ->latest('created_at')
                ->get(),
        ]);
    }

    public function moderate(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,unlisted,changes_required,rejected'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $product, $data): void {
            $product = Product::query()->with('store.tenant')->lockForUpdate()->findOrFail($product->id);
            $oldStatus = $product->status;
            if ($data['status'] === 'active' && ($product->store->status !== 'active' || $product->store->tenant->status !== 'active')) {
                throw ValidationException::withMessages(['status' => 'The store and seller must be approved and active before publication.']);
            }
            if ($data['status'] === 'active' && $oldStatus !== 'active' && (! filled($product->description) || $product->category_id === null || ! $product->images()->exists())) {
                throw ValidationException::withMessages(['status' => 'Add a category, description, and product photo before activating this listing.']);
            }
            if ($data['status'] === 'active' && $product->hasVariantsWithoutPhotos()) {
                throw ValidationException::withMessages(['status' => 'Each variant needs photos or a shared product photo before publication.']);
            }
            if (! array_key_exists($data['status'], $product->moderationOptions())) {
                throw ValidationException::withMessages(['status' => 'This decision is not available for the current listing state.']);
            }
            $product->update(['status' => $data['status'], 'moderation_reason' => $data['reason'], 'content_changed_at' => null]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'product.moderated',
                'entity_type' => Product::class,
                'entity_id' => $product->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $data['status'], 'reason' => $data['reason']],
                'ip_address' => $request->ip(),
            ]);
        });

        return to_route('admin.products.show', $product)->with('status', 'Listing moderation decision recorded.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
    }
}
