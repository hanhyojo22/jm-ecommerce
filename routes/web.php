<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GovernanceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Admin\StoreModerationController;
use App\Http\Controllers\Marketplace\CartController;
use App\Http\Controllers\Marketplace\CatalogController;
use App\Http\Controllers\Marketplace\CheckoutController;
use App\Http\Controllers\Marketplace\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\SellerOrderController;
use App\Http\Controllers\SellerApplicationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/stores/{store}', [CatalogController::class, 'store'])->name('catalog.store');
Route::get('/stores/{store}/products/{product}', [CatalogController::class, 'show'])->name('catalog.show');

Route::middleware(['auth', 'verified', 'account:buyer'])->group(function (): void {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/products/{product}', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/cart/restore', [CartController::class, 'restore'])->name('cart.restore');
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified', 'account:admin'])->name('dashboard');

Route::middleware(['auth', 'account:buyer,seller,admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'account:seller'])->group(function (): void {
    Route::get('/seller', [SellerDashboardController::class, 'index'])->name('seller.dashboard');
    Route::get('/start-selling', [SellerApplicationController::class, 'create'])->name('seller-application.create');
    Route::post('/start-selling', [SellerApplicationController::class, 'store'])->name('seller-application.store');

    Route::get('/seller/products', [ProductController::class, 'index'])->name('seller.products.index');
    Route::get('/seller/products/create', [ProductController::class, 'create'])->name('seller.products.create');
    Route::post('/seller/products', [ProductController::class, 'store'])->name('seller.products.store');
    Route::get('/seller/products/{product}/edit', [ProductController::class, 'edit'])->name('seller.products.edit');
    Route::patch('/seller/products/{product}', [ProductController::class, 'update'])->name('seller.products.update');
    Route::get('/seller/orders', [SellerOrderController::class, 'index'])->name('seller.orders.index');
    Route::get('/seller/orders/{sellerOrder}', [SellerOrderController::class, 'show'])->name('seller.orders.show');
    Route::patch('/seller/orders/{sellerOrder}', [SellerOrderController::class, 'update'])->name('seller.orders.update');

});

Route::middleware(['auth', 'verified', 'account:admin'])->group(function (): void {
    Route::get('/admin/seller-applications', [AdminSellerApplicationController::class, 'index'])->name('admin.seller-applications.index');
    Route::get('/admin/seller-applications/{sellerApplication}', [AdminSellerApplicationController::class, 'show'])->name('admin.seller-applications.show');
    Route::post('/admin/seller-applications/{sellerApplication}/approve', [AdminSellerApplicationController::class, 'approve'])->name('admin.seller-applications.approve');
    Route::post('/admin/seller-applications/{sellerApplication}/reject', [AdminSellerApplicationController::class, 'reject'])->name('admin.seller-applications.reject');
    Route::get('/admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/admin/products', [AdminProductController::class, 'index'])->name('admin.products.index');
    Route::get('/admin/products/{product}', [AdminProductController::class, 'show'])->name('admin.products.show');
    Route::get('/admin/stores', [GovernanceController::class, 'stores'])->name('admin.stores.index');
    Route::get('/admin/stores/{store}', [GovernanceController::class, 'showStore'])->name('admin.stores.show');
    Route::patch('/admin/stores/{store}/suspend', [StoreModerationController::class, 'suspend'])->name('admin.stores.suspend');
    Route::patch('/admin/stores/{store}/reactivate', [StoreModerationController::class, 'reactivate'])->name('admin.stores.reactivate');
    Route::get('/admin/customers', [GovernanceController::class, 'customers'])->name('admin.customers.index');
    Route::get('/admin/customers/{customer}', [GovernanceController::class, 'showCustomer'])->name('admin.customers.show');
    Route::get('/admin/audit-logs', [GovernanceController::class, 'auditLogs'])->name('admin.audit-logs.index');
    Route::get('/admin/accounts', [AdminAccountController::class, 'index'])->name('admin.accounts.index');
    Route::post('/admin/accounts', [AdminAccountController::class, 'store'])->name('admin.accounts.store');
    Route::patch('/admin/accounts/{user}/deactivate', [AdminAccountController::class, 'deactivate'])->name('admin.accounts.deactivate');
});

require __DIR__.'/auth.php';
