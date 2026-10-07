<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $tenant = $this->activeTenant($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'status' => ['nullable', 'in:active,draft,inactive'],
            'stock' => ['nullable', 'in:in_stock,out_of_stock'],
            'sort' => ['nullable', 'in:newest,name,price,stock'],
        ]);
        $products = $tenant->store->products()
            ->with(['images', 'category:id,name'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';

                $query->where(function ($productQuery) use ($search): void {
                    $productQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$search]);
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, string $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['stock'] ?? null, function ($query, string $stock): void {
                $query->where('stock_quantity', $stock === 'out_of_stock' ? '=' : '>', 0);
            });

        match ($filters['sort'] ?? 'newest') {
            'name' => $products->orderBy('name'),
            'price' => $products->orderBy('price'),
            'stock' => $products->orderBy('stock_quantity'),
            default => $products->latest(),
        };

        return view('seller.products.index', [
            'products' => $products->paginate(20)->withQueryString(),
            'store' => $tenant->store,
            'filters' => $filters,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'productCounts' => $tenant->store->products()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'outOfStockCount' => $tenant->store->products()->where('stock_quantity', 0)->count(),
            'canManageProducts' => $this->canManageProducts($request, $tenant),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Product::class);

        return view('seller.products.create', ['store' => $this->activeTenant($request)->store, 'categories' => Category::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        $store = $tenant->store;
        $data = $request->validated();
        unset($data['image']);

        $product = $store->products()->create([
            ...$data,
            'tenant_id' => $tenant->id,
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
        ]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'product_images');
            $product->images()->create(['path' => $path]);
        }

        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'product.created',
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'new_values' => $product->only(['name', 'sku', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status']),
            'ip_address' => $request->ip(),
        ]);

        return to_route('seller.products.index')->with('status', "{$product->name} was saved.");
    }

    public function edit(Request $request, Product $product): View
    {
        $product = $this->editableProduct($request, $product);

        return view('seller.products.edit', ['product' => $product->load('images'), 'store' => $this->activeTenant($request)->store, 'categories' => Category::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product = $this->editableProduct($request, $product);
        $oldValues = $product->only(['name', 'sku', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status']);
        $data = $request->validated();
        unset($data['image']);
        $product->update($data);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'product_images');
            $product->images()->create(['path' => $path, 'position' => $product->images()->count()]);
        }

        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'product.updated',
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'old_values' => $oldValues,
            'new_values' => $product->only(['name', 'sku', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status']),
            'ip_address' => $request->ip(),
        ]);

        return to_route('seller.products.index')->with('status', "{$product->name} was updated.");
    }

    private function editableProduct(Request $request, Product $product): Product
    {
        $tenant = $this->activeTenant($request);
        $product = $tenant->store->products()->whereKey($product)->firstOrFail();
        $this->authorize('update', $product);

        return $product;
    }

    private function canManageProducts(Request $request, Tenant $tenant): bool
    {
        return $request->user()->tenantMemberships()->where('tenant_id', $tenant->id)->whereIn('role', ['owner', 'manager'])->exists();
    }

    private function activeTenant(Request $request): Tenant
    {
        return $request->user()->sellerTenant() ?? abort(403);
    }
}
