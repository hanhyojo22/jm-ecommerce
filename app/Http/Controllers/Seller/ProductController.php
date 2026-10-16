<?php

namespace App\Http\Controllers\Seller;

use App\Actions\StorePublicImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $tenant = $this->activeTenant($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'status' => ['nullable', 'in:active,draft,inactive,pending,changes_required,rejected'],
            'stock' => ['nullable', 'in:in_stock,out_of_stock'],
            'sort' => ['nullable', 'in:newest,name,price,stock'],
        ]);
        $products = $tenant->store->products()
            ->with(['images', 'category:id,name'])
            ->withCount('variants')
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
            'productCounts' => $tenant->store->products()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'canManageProducts' => $this->canManageProducts($request, $tenant),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Product::class);

        return view('seller.products.create', ['store' => $this->activeTenant($request)->store, 'categories' => Category::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function show(Request $request, Product $product): View
    {
        $tenant = $this->activeTenant($request);
        $product = $tenant->store->products()->whereKey($product)->firstOrFail();
        $this->authorize('view', $product);

        return view('seller.products.show', [
            'product' => $product->load(['images', 'variants.images', 'category:id,name']),
            'canManageProducts' => $this->canManageProducts($request, $tenant),
        ]);
    }

    public function store(StoreProductRequest $request, StorePublicImage $storePublicImage): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        $store = $tenant->store;
        $data = $request->validated();
        $variants = $data['variants'] ?? [];
        unset($data['image'], $data['images'], $data['variants']);
        if ($variants !== []) {
            $data['stock_quantity'] = array_sum(array_column($variants, 'stock_quantity'));
        }
        $imagePaths = [];
        $variantImagePaths = [];

        try {
            foreach (array_filter([$request->file('image'), ...($request->file('images') ?? [])]) as $image) {
                $imagePaths[] = $storePublicImage->handle($image, 'products', 'product_images');
            }

            foreach ($variants as $index => $variant) {
                foreach (($request->file("variants.$index.images") ?? []) as $image) {
                    $variantImagePaths[$index][] = $storePublicImage->handle($image, 'products', 'product_images');
                }
                unset($variants[$index]['images']);
            }

            $product = DB::transaction(function () use ($request, $store, $tenant, $data, $variants, $imagePaths, $variantImagePaths): Product {
                $product = $store->products()->create([
                    ...$data,
                    'tenant_id' => $tenant->id,
                    'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                    'status' => 'draft',
                ]);
                foreach ($imagePaths as $position => $imagePath) {
                    $product->images()->create(['path' => $imagePath, 'position' => $position]);
                }
                $position = count($imagePaths);
                foreach ($variants as $index => $variant) {
                    $savedVariant = $product->variants()->create($variant);
                    foreach ($variantImagePaths[$index] ?? [] as $path) {
                        $product->images()->create(['path' => $path, 'position' => $position++, 'product_variant_id' => $savedVariant->id]);
                    }
                }

                AuditLog::query()->create([
                    'actor_user_id' => $request->user()->id,
                    'action' => 'product.created',
                    'entity_type' => Product::class,
                    'entity_id' => $product->id,
                    'new_values' => $product->only(['name', 'sku', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status']),
                    'ip_address' => $request->ip(),
                ]);

                return $product;
            });
        } catch (Throwable $exception) {
            foreach ([...$imagePaths, ...array_merge([], ...array_values($variantImagePaths))] as $imagePath) {
                $this->deleteUploadedImage($imagePath);
            }

            throw $exception;
        }

        return to_route('seller.products.index')->with('status', "{$product->name} was saved.");
    }

    public function edit(Request $request, Product $product): View
    {
        $product = $this->editableProduct($request, $product);

        return view('seller.products.edit', ['product' => $product->load(['images', 'variants.images']), 'store' => $this->activeTenant($request)->store, 'categories' => Category::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(UpdateProductRequest $request, Product $product, StorePublicImage $storePublicImage): RedirectResponse
    {
        $product = $this->editableProduct($request, $product);
        $data = $request->validated();
        $variants = $data['variants'] ?? [];
        unset($data['image'], $data['images'], $data['variants']);
        if ($variants !== []) {
            $data['stock_quantity'] = array_sum(array_column($variants, 'stock_quantity'));
        }
        $imagePaths = [];
        $variantImagePaths = [];

        try {
            foreach (array_filter([$request->file('image'), ...($request->file('images') ?? [])]) as $image) {
                $imagePaths[] = $storePublicImage->handle($image, 'products', 'product_images');
            }

            foreach ($variants as $index => $variant) {
                foreach (($request->file("variants.$index.images") ?? []) as $image) {
                    $variantImagePaths[$index][] = $storePublicImage->handle($image, 'products', 'product_images');
                }
                unset($variants[$index]['images']);
            }

            DB::transaction(function () use ($request, $product, $data, $variants, $imagePaths, $variantImagePaths): void {
                $product = Product::query()->lockForUpdate()->findOrFail($product->id);

                if ($product->status !== 'active' && $data['status'] === 'active') {
                    abort(403, 'Only an administrator can activate a listing.');
                }

                if (in_array($product->status, ['pending', 'inactive'], true)) {
                    throw ValidationException::withMessages(['status' => 'This listing cannot be edited in its current review state.']);
                }

                $wasComplete = filled($product->description) && $product->category_id !== null && $product->images()->exists();
                if ($data['status'] === 'active' && $wasComplete && (! filled($data['description'] ?? null) || empty($data['category_id']))) {
                    throw ValidationException::withMessages(['status' => 'An active listing needs a category, description, and product photo.']);
                }

                $oldValues = $product->only(['name', 'sku', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status']);
                $data['status'] = $data['status'] === 'inactive' ? 'inactive' : 'draft';
                $product->update($data);

                $position = $product->images()->count();
                foreach ($imagePaths as $imagePath) {
                    $product->images()->create(['path' => $imagePath, 'position' => $position++]);
                }
                foreach ($variants as $index => $variant) {
                    $id = $variant['id'] ?? null;
                    unset($variant['id']);
                    if ($id !== null) {
                        $savedVariant = $product->variants()->whereKey($id)->firstOrFail();
                        $savedVariant->update($variant);
                    } else {
                        $savedVariant = $product->variants()->create($variant);
                    }
                    foreach ($variantImagePaths[$index] ?? [] as $path) {
                        $product->images()->create(['path' => $path, 'position' => $position++, 'product_variant_id' => $savedVariant->id]);
                    }
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
            });
        } catch (Throwable $exception) {
            foreach ([...$imagePaths, ...array_merge([], ...array_values($variantImagePaths))] as $imagePath) {
                $this->deleteUploadedImage($imagePath);
            }

            throw $exception;
        }

        return to_route('seller.products.index')->with('status', "{$product->name} was updated.");
    }

    public function submit(Request $request, Product $product): RedirectResponse
    {
        $product = $this->editableProduct($request, $product);
        DB::transaction(function () use ($request, $product): void {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! in_array($product->status, ['draft', 'changes_required', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'Only a draft or returned listing can be submitted for review.']);
            }
            if (! filled($product->description) || $product->category_id === null || ! $product->images()->exists()) {
                throw ValidationException::withMessages(['status' => 'Add a category, description, and product photo before submitting for review.']);
            }
            if (! $product->images()->whereNull('product_variant_id')->exists() && $product->variants()->whereDoesntHave('images')->exists()) {
                throw ValidationException::withMessages(['status' => 'Add photos for every variant or upload a shared product photo before submitting.']);
            }
            $oldStatus = $product->status;
            $product->update(['status' => 'pending', 'moderation_reason' => null]);
            AuditLog::query()->create(['actor_user_id' => $request->user()->id, 'action' => 'product.submitted', 'entity_type' => Product::class, 'entity_id' => $product->id, 'old_values' => ['status' => $oldStatus], 'new_values' => ['status' => 'pending'], 'ip_address' => $request->ip()]);
        });

        return to_route('seller.products.index')->with('status', 'Listing submitted for administrator review.');
    }

    public function bulkDeactivate(Request $request): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        abort_unless($this->canManageProducts($request, $tenant), 403);

        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:40'],
            'product_ids.*' => ['required', 'ulid'],
        ]);
        $productIds = collect($data['product_ids'])->unique()->values();

        if ($productIds->count() > 20) {
            throw ValidationException::withMessages([
                'product_ids' => 'Select no more than 20 listings at a time.',
            ]);
        }

        $products = DB::transaction(function () use ($request, $tenant, $productIds) {
            $products = $tenant->store->products()
                ->whereKey($productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($products->count() !== $productIds->count() || $products->contains(fn (Product $product): bool => $product->status !== 'active')) {
                throw ValidationException::withMessages([
                    'product_ids' => 'Some selected listings changed or are no longer active in your store. Refresh and try again.',
                ]);
            }

            foreach ($products as $product) {
                $oldStatus = $product->status;
                $product->update(['status' => 'inactive']);

                AuditLog::query()->create([
                    'actor_user_id' => $request->user()->id,
                    'action' => 'product.updated',
                    'entity_type' => Product::class,
                    'entity_id' => $product->id,
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => 'inactive'],
                    'ip_address' => $request->ip(),
                ]);
            }

            return $products;
        });

        return to_route('seller.products.index')->with('status', count($products).' '.Str::plural('listing', count($products)).' '.(count($products) === 1 ? 'was' : 'were').' deactivated.');
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

    private function deleteUploadedImage(?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            Storage::disk('product_images')->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function activeTenant(Request $request): Tenant
    {
        return $request->user()->sellerTenant() ?? abort(403);
    }
}
