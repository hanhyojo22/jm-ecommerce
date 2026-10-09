<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\ChatReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GovernanceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductReviewModerationController;
use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Admin\SellerPayoutController;
use App\Http\Controllers\Admin\StoreModerationController;
use App\Http\Controllers\ClerkWebhookController;
use App\Http\Controllers\Marketplace\CartController;
use App\Http\Controllers\Marketplace\CatalogController;
use App\Http\Controllers\Marketplace\CheckoutController;
use App\Http\Controllers\Marketplace\ConversationAttachmentController;
use App\Http\Controllers\Marketplace\ConversationController;
use App\Http\Controllers\Marketplace\OrderController;
use App\Http\Controllers\Marketplace\ProductReviewController as MarketplaceProductReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\EarningController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\ProductReviewController as SellerProductReviewController;
use App\Http\Controllers\Seller\SellerOrderController;
use App\Http\Controllers\Seller\StoreSettingsController;
use App\Http\Controllers\SellerApplicationController;
use App\Http\Controllers\SellerSuspensionAppealController;
use App\Livewire\Chat\Inbox as ChatInbox;
use App\Livewire\Chat\Thread as ChatThread;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/search', [CatalogController::class, 'index'])->name('catalog.index');
Route::delete('/recently-viewed', [CatalogController::class, 'clearRecentlyViewed'])->name('catalog.recently-viewed.clear');
Route::get('/top-products', [CatalogController::class, 'topProducts'])->name('catalog.top-products');
Route::get('/stores', [CatalogController::class, 'stores'])->name('catalog.stores');
Route::get('/stores/{store}', [CatalogController::class, 'store'])->name('catalog.store');
Route::get('/stores/{store}/products/{product}', [CatalogController::class, 'show'])->name('catalog.show');
Route::post('/clerk/webhook', ClerkWebhookController::class)->middleware('throttle:60,1')->name('clerk.webhook');

Route::middleware(['auth', 'clerk.verified', 'account:buyer'])->group(function (): void {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/products/{product}', [CartController::class, 'store'])->middleware('throttle:cart')->name('cart.store');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->middleware('throttle:cart')->name('cart.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->middleware('throttle:cart')->name('cart.destroy');
    Route::post('/cart/restore', [CartController::class, 'restore'])->middleware('throttle:cart')->name('cart.restore');
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/order-items/{orderItem}/review', [MarketplaceProductReviewController::class, 'store'])->middleware('throttle:10,1')->name('reviews.store');
    Route::patch('/reviews/{review}', [MarketplaceProductReviewController::class, 'update'])->middleware('throttle:10,1')->name('reviews.update');
    Route::delete('/reviews/{review}', [MarketplaceProductReviewController::class, 'destroy'])->middleware('throttle:10,1')->name('reviews.destroy');
    Route::post('/reviews/{review}/report', [MarketplaceProductReviewController::class, 'report'])->middleware('throttle:10,1')->name('reviews.report');
    Route::post('/messages/products/{product}', [ConversationController::class, 'startForProduct'])->middleware('throttle:10,1')->name('messages.products.start');
    Route::post('/messages/seller-orders/{sellerOrder}', [ConversationController::class, 'startForSellerOrder'])->middleware('throttle:10,1')->name('messages.seller-orders.start');
});

Route::middleware(['auth', 'clerk.verified', 'account:buyer,seller'])->group(function (): void {
    Route::get('/messages', ChatInbox::class)->name('messages.index');
    Route::get('/messages/{conversation}', ChatThread::class)->name('messages.show');
    Route::post('/messages/{conversation}/block', [ConversationController::class, 'block'])->middleware('throttle:10,1')->name('messages.block');
    Route::post('/messages/{conversation}/messages/{message}/report', [ConversationController::class, 'report'])->middleware('throttle:10,1')->name('messages.report');
    Route::post('/messages/{conversation}/attachments', [ConversationAttachmentController::class, 'store'])->middleware('throttle:10,1')->name('messages.attachments.store');
    Route::get('/messages/attachments/{attachment}', [ConversationAttachmentController::class, 'show'])->name('messages.attachments.show');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'clerk.verified', 'account:admin'])->name('dashboard');
Route::get('/admin/moderation', [DashboardController::class, 'moderation'])->middleware(['auth', 'clerk.verified', 'account:admin'])->name('admin.moderation');

Route::middleware(['auth', 'account:buyer,seller,admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

Route::middleware(['auth', 'clerk.verified', 'account:buyer,seller'])->group(function (): void {
    Route::get('/start-selling', [SellerApplicationController::class, 'create'])->name('seller-application.create');
    Route::post('/start-selling', [SellerApplicationController::class, 'store'])->middleware('throttle:10,1')->name('seller-application.store');
});

Route::middleware(['auth', 'clerk.verified', 'account:seller'])->group(function (): void {
    Route::get('/seller', [SellerDashboardController::class, 'index'])->name('seller.dashboard');
    Route::get('/seller/store-settings', [StoreSettingsController::class, 'edit'])->name('seller.store-settings.edit');
    Route::patch('/seller/store-settings', [StoreSettingsController::class, 'update'])->middleware('throttle:10,1')->name('seller.store-settings.update');
    Route::post('/seller/store-settings/undo', [StoreSettingsController::class, 'undo'])->middleware('throttle:10,1')->name('seller.store-settings.undo');
    Route::get('/seller/products', [ProductController::class, 'index'])->name('seller.products.index');
    Route::post('/seller/products/bulk-deactivate', [ProductController::class, 'bulkDeactivate'])->middleware('throttle:10,1')->name('seller.products.bulk-deactivate');
    Route::get('/seller/products/create', [ProductController::class, 'create'])->name('seller.products.create');
    Route::post('/seller/products', [ProductController::class, 'store'])->middleware('throttle:10,1')->name('seller.products.store');
    Route::get('/seller/products/{product}/edit', [ProductController::class, 'edit'])->name('seller.products.edit');
    Route::patch('/seller/products/{product}', [ProductController::class, 'update'])->middleware('throttle:10,1')->name('seller.products.update');
    Route::get('/seller/orders', [SellerOrderController::class, 'index'])->name('seller.orders.index');
    Route::post('/seller/orders/bulk-confirm', [SellerOrderController::class, 'bulkConfirm'])->middleware('throttle:10,1')->name('seller.orders.bulk-confirm');
    Route::get('/seller/orders/{sellerOrder}', [SellerOrderController::class, 'show'])->name('seller.orders.show');
    Route::patch('/seller/orders/{sellerOrder}', [SellerOrderController::class, 'update'])->middleware('throttle:10,1')->name('seller.orders.update');
    Route::get('/seller/earnings', [EarningController::class, 'index'])->name('seller.earnings.index');
    Route::get('/seller/reviews', [SellerProductReviewController::class, 'index'])->name('seller.reviews.index');
    Route::post('/seller/reviews/{review}/response', [SellerProductReviewController::class, 'respond'])->middleware('throttle:10,1')->name('seller.reviews.respond');

});

Route::post('/seller/suspension-appeals', [SellerSuspensionAppealController::class, 'store'])
    ->middleware(['auth', 'clerk.verified', 'account:seller', 'throttle:5,1'])
    ->name('seller.suspension-appeals.store');

Route::middleware(['auth', 'clerk.verified', 'account:admin'])->group(function (): void {
    Route::get('/admin/chat-reports', [ChatReportController::class, 'index'])->name('admin.chat-reports.index');
    Route::patch('/admin/chat-reports/{report}', [ChatReportController::class, 'update'])->middleware('throttle:admin-mutations')->name('admin.chat-reports.update');
    Route::get('/admin/seller-applications', [AdminSellerApplicationController::class, 'index'])->name('admin.seller-applications.index');
    Route::get('/admin/seller-applications/{sellerApplication}', [AdminSellerApplicationController::class, 'show'])->name('admin.seller-applications.show');
    Route::post('/admin/seller-applications/{sellerApplication}/approve', [AdminSellerApplicationController::class, 'approve'])->middleware('throttle:admin-mutations')->name('admin.seller-applications.approve');
    Route::post('/admin/seller-applications/{sellerApplication}/reject', [AdminSellerApplicationController::class, 'reject'])->middleware('throttle:admin-mutations')->name('admin.seller-applications.reject');
    Route::get('/admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    Route::patch('/admin/orders/{order}/payment/mark-paid', [AdminOrderController::class, 'markCodPaymentPaid'])->middleware('throttle:admin-mutations')->name('admin.orders.payment.mark-paid');
    Route::get('/admin/payouts', [SellerPayoutController::class, 'index'])->name('admin.payouts.index');
    Route::post('/admin/payouts', [SellerPayoutController::class, 'store'])->middleware('throttle:admin-mutations')->name('admin.payouts.store');
    Route::get('/admin/products', [AdminProductController::class, 'index'])->name('admin.products.index');
    Route::get('/admin/products/{product}', [AdminProductController::class, 'show'])->name('admin.products.show');
    Route::patch('/admin/products/{product}', [AdminProductController::class, 'moderate'])->middleware('throttle:admin-mutations')->name('admin.products.moderate');
    Route::get('/admin/stores', [GovernanceController::class, 'stores'])->name('admin.stores.index');
    Route::get('/admin/stores/{store}', [GovernanceController::class, 'showStore'])->name('admin.stores.show');
    Route::patch('/admin/stores/{store}/suspend', [StoreModerationController::class, 'suspend'])->middleware('throttle:admin-mutations')->name('admin.stores.suspend');
    Route::patch('/admin/stores/{store}/reactivate', [StoreModerationController::class, 'reactivate'])->middleware('throttle:admin-mutations')->name('admin.stores.reactivate');
    Route::patch('/admin/stores/{store}/appeals/{appeal}', [StoreModerationController::class, 'updateAppeal'])
        ->middleware('throttle:admin-mutations')
        ->scopeBindings()
        ->name('admin.stores.appeals.update');
    Route::patch('/admin/stores/{store}/appeals/{appeal}/reopen', [StoreModerationController::class, 'reopenAppeal'])
        ->middleware('throttle:admin-mutations')
        ->scopeBindings()
        ->name('admin.stores.appeals.reopen');
    Route::get('/admin/customers', [GovernanceController::class, 'customers'])->name('admin.customers.index');
    Route::get('/admin/customers/{customer}', [GovernanceController::class, 'showCustomer'])->name('admin.customers.show');
    Route::get('/admin/audit-logs', [GovernanceController::class, 'auditLogs'])->name('admin.audit-logs.index');
    Route::get('/admin/accounts', [AdminAccountController::class, 'index'])->name('admin.accounts.index');
    Route::post('/admin/accounts', [AdminAccountController::class, 'store'])->middleware('throttle:admin-mutations')->name('admin.accounts.store');
    Route::patch('/admin/accounts/{user}/deactivate', [AdminAccountController::class, 'deactivate'])->middleware('throttle:admin-mutations')->name('admin.accounts.deactivate');
    Route::get('/admin/reviews', [ProductReviewModerationController::class, 'index'])->name('admin.reviews.index');
    Route::patch('/admin/reviews/{review}', [ProductReviewModerationController::class, 'moderate'])->middleware('throttle:admin-mutations')->name('admin.reviews.moderate');
});

require __DIR__.'/auth.php';
