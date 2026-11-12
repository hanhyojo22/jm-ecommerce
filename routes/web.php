<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ChatReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GovernanceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrderIssueController as AdminOrderIssueController;
use App\Http\Controllers\Admin\PlatformFeeController as AdminPlatformFeeController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductReviewModerationController;
use App\Http\Controllers\Admin\ReturnCaseController as AdminReturnCaseController;
use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Admin\SellerPayoutController;
use App\Http\Controllers\Admin\StoreModerationController;
use App\Http\Controllers\ClerkWebhookController;
use App\Http\Controllers\CookiePreferencesController;
use App\Http\Controllers\Marketplace\CartController;
use App\Http\Controllers\Marketplace\CatalogController;
use App\Http\Controllers\Marketplace\CheckoutController;
use App\Http\Controllers\Marketplace\ConversationAttachmentController;
use App\Http\Controllers\Marketplace\ConversationController;
use App\Http\Controllers\Marketplace\DeliveryAddressController;
use App\Http\Controllers\Marketplace\OrderController;
use App\Http\Controllers\Marketplace\OrderIssueController;
use App\Http\Controllers\Marketplace\ProductDiscussionController;
use App\Http\Controllers\Marketplace\ProductReviewController as MarketplaceProductReviewController;
use App\Http\Controllers\Marketplace\ReturnCaseController as BuyerReturnCaseController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\EarningController;
use App\Http\Controllers\Seller\OnlinePaymentsController;
use App\Http\Controllers\Seller\PlatformFeeController as SellerPlatformFeeController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\ProductReviewController as SellerProductReviewController;
use App\Http\Controllers\Seller\ReturnCaseController as SellerReturnCaseController;
use App\Http\Controllers\Seller\SellerOrderController;
use App\Http\Controllers\Seller\StoreSettingsController;
use App\Http\Controllers\SellerApplicationController;
use App\Http\Controllers\SellerSuspensionAppealController;
use App\Http\Controllers\WebPushKeyController;
use App\Http\Controllers\WorkspacePreferenceController;
use App\Livewire\Chat\Inbox as ChatInbox;
use App\Livewire\Chat\Thread as ChatThread;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');
Route::view('/terms', 'terms')->name('terms');
Route::view('/returns', 'returns')->name('returns');
Route::view('/cookie-policy', 'cookie-policy')->name('cookie-policy');
Route::post('/cookie-preferences', CookiePreferencesController::class)->middleware('throttle:10,1')->name('cookie-preferences.store');
Route::get('/search', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/search/suggestions', [CatalogController::class, 'suggestions'])->name('catalog.suggestions');
Route::delete('/recently-viewed', [CatalogController::class, 'clearRecentlyViewed'])->name('catalog.recently-viewed.clear');
Route::get('/top-products', [CatalogController::class, 'topProducts'])->name('catalog.top-products');
Route::get('/stores', [CatalogController::class, 'stores'])->name('catalog.stores');
Route::get('/stores/{store}', [CatalogController::class, 'store'])->name('catalog.store');
Route::get('/stores/{store}/products/{product}', [CatalogController::class, 'show'])->name('catalog.show');
Route::post('/clerk/webhook', ClerkWebhookController::class)->middleware('throttle:60,1')->name('clerk.webhook');
Route::post('/paymongo/webhook', PayMongoWebhookController::class)->middleware('throttle:120,1')->name('paymongo.webhook');
Route::get('/push/vapid-public-key', WebPushKeyController::class)->middleware('throttle:60,1')->name('push.vapid-key');
Route::get('/push-notifications/{notification}/read', [PushNotificationController::class, 'markRead'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('push-notifications.read');

Route::middleware(['auth', 'clerk.verified', 'account:buyer,seller'])->group(function (): void {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/products/{product}', [CartController::class, 'store'])->middleware('throttle:cart')->name('cart.store');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->middleware('throttle:cart')->name('cart.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->middleware('throttle:cart')->name('cart.destroy');
    Route::patch('/cart/selection', [CartController::class, 'updateSelection'])->middleware('throttle:cart-selection')->name('cart.selection');
    Route::delete('/cart/selected', [CartController::class, 'destroySelected'])->middleware('throttle:cart')->name('cart.destroy-selected');
    Route::post('/cart/restore', [CartController::class, 'restore'])->middleware('throttle:cart')->name('cart.restore');
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/placed', [OrderController::class, 'placed'])->name('orders.placed');
    Route::get('/orders/{order}/pay', [OrderController::class, 'pay'])->middleware('throttle:10,1')->name('orders.pay');
    Route::post('/orders/seller-orders/{sellerOrder}/received', [OrderController::class, 'received'])->middleware('throttle:10,1')->name('orders.received');
    Route::post('/orders/store-orders/{sellerOrder}/issues', [OrderIssueController::class, 'store'])->middleware('throttle:10,1')->name('order-issues.store');
    Route::post('/orders/store-orders/{sellerOrder}/returns', [BuyerReturnCaseController::class, 'store'])->middleware('throttle:10,1')->name('returns.store');
    Route::get('/orders/returns/{returnCase}', [BuyerReturnCaseController::class, 'show'])->name('returns.show');
    Route::patch('/orders/returns/{returnCase}', [BuyerReturnCaseController::class, 'update'])->middleware('throttle:10,1')->name('returns.update');
    Route::get('/my-reviews', [MarketplaceProductReviewController::class, 'index'])->name('reviews.index');
    Route::get('/delivery-addresses', [DeliveryAddressController::class, 'index'])->name('delivery-addresses.index');
    Route::post('/delivery-addresses', [DeliveryAddressController::class, 'store'])->middleware('throttle:10,1')->name('delivery-addresses.store');
    Route::get('/delivery-addresses/{deliveryAddress}/edit', [DeliveryAddressController::class, 'edit'])->name('delivery-addresses.edit');
    Route::patch('/delivery-addresses/{deliveryAddress}', [DeliveryAddressController::class, 'update'])->middleware('throttle:10,1')->name('delivery-addresses.update');
    Route::delete('/delivery-addresses/{deliveryAddress}', [DeliveryAddressController::class, 'destroy'])->middleware('throttle:10,1')->name('delivery-addresses.destroy');
    Route::post('/order-items/{orderItem}/review', [MarketplaceProductReviewController::class, 'store'])->middleware('throttle:10,1')->name('reviews.store');
    Route::post('/products/{product}/discussions', [ProductDiscussionController::class, 'store'])->middleware('throttle:10,1')->name('discussions.store');
    Route::patch('/reviews/{review}', [MarketplaceProductReviewController::class, 'update'])->middleware('throttle:10,1')->name('reviews.update');
    Route::delete('/reviews/{review}', [MarketplaceProductReviewController::class, 'destroy'])->middleware('throttle:10,1')->name('reviews.destroy');
    Route::post('/reviews/{review}/report', [MarketplaceProductReviewController::class, 'report'])->middleware('throttle:10,1')->name('reviews.report');
    Route::post('/messages/products/{product}', [ConversationController::class, 'startForProduct'])->middleware('throttle:10,1')->name('messages.products.start');
    Route::post('/messages/seller-orders/{sellerOrder}', [ConversationController::class, 'startForSellerOrder'])->middleware('throttle:10,1')->name('messages.seller-orders.start');
});

Route::middleware(['auth', 'clerk.verified'])->group(function (): void {
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('push-subscriptions.destroy');
});

Route::middleware(['auth', 'clerk.verified', 'account:buyer,seller'])->group(function (): void {
    Route::get('/messages', ChatInbox::class)->name('messages.index');
    Route::get('/messages/{conversation}', ChatThread::class)->name('messages.show');
    Route::post('/messages/{conversation}/block', [ConversationController::class, 'block'])->middleware('throttle:10,1')->name('messages.block');
    Route::delete('/messages/{conversation}/block', [ConversationController::class, 'unblock'])->middleware('throttle:10,1')->name('messages.unblock');
    Route::post('/messages/{conversation}/messages/{message}/report', [ConversationController::class, 'report'])->middleware('throttle:10,1')->name('messages.report');
    Route::post('/messages/{conversation}/attachments', [ConversationAttachmentController::class, 'store'])->middleware('throttle:10,1')->name('messages.attachments.store');
    Route::get('/messages/attachments/{attachment}', [ConversationAttachmentController::class, 'show'])->name('messages.attachments.show');
});

Route::get('/returns/cases/{returnCase}/evidence/{evidence}', [BuyerReturnCaseController::class, 'evidence'])
    ->middleware(['auth', 'clerk.verified', 'account:buyer,seller,admin'])->name('returns.evidence');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'clerk.verified', 'account:admin', 'platform-admin'])->name('dashboard');
Route::get('/admin/moderation', [DashboardController::class, 'moderation'])->middleware(['auth', 'clerk.verified', 'account:admin', 'platform-admin'])->name('admin.moderation');

Route::middleware(['auth', 'account:buyer,seller,admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

Route::post('/profile/workspace', WorkspacePreferenceController::class)
    ->middleware(['auth', 'account:buyer,seller'])
    ->name('profile.workspace.update');

Route::patch('/profile/notifications', NotificationPreferenceController::class)
    ->middleware(['auth', 'account:buyer,seller'])
    ->name('profile.notifications.update');

Route::middleware(['auth', 'clerk.verified', 'account:buyer,seller'])->group(function (): void {
    Route::get('/start-selling', [SellerApplicationController::class, 'create'])->name('seller-application.create');
    Route::get('/start-selling/store-link', [SellerApplicationController::class, 'storeLink'])->middleware('throttle:30,1')->name('seller-application.store-link');
    Route::post('/start-selling', [SellerApplicationController::class, 'store'])->middleware('throttle:10,1')->name('seller-application.store');
    Route::post('/start-selling/steps/{step}', [SellerApplicationController::class, 'saveStep'])->middleware('throttle:30,1')->whereNumber('step')->name('seller-application.step');
});

Route::middleware(['auth', 'clerk.verified', 'account:seller'])->group(function (): void {
    Route::get('/seller', [SellerDashboardController::class, 'index'])->name('seller.dashboard');
    Route::get('/seller/store-settings', [StoreSettingsController::class, 'edit'])->name('seller.store-settings.edit');
    Route::patch('/seller/store-settings', [StoreSettingsController::class, 'update'])->middleware('throttle:10,1')->name('seller.store-settings.update');
    Route::post('/seller/online-payments', [OnlinePaymentsController::class, 'store'])->middleware('throttle:10,1')->name('seller.online-payments.store');
    Route::post('/seller/online-payments/refresh', [OnlinePaymentsController::class, 'refresh'])->middleware('throttle:10,1')->name('seller.online-payments.refresh');
    Route::post('/seller/store-settings/undo', [StoreSettingsController::class, 'undo'])->middleware('throttle:10,1')->name('seller.store-settings.undo');
    Route::get('/seller/products', [ProductController::class, 'index'])->name('seller.products.index');
    Route::post('/seller/products/bulk-deactivate', [ProductController::class, 'bulkDeactivate'])->middleware('throttle:10,1')->name('seller.products.bulk-deactivate');
    Route::get('/seller/products/create', [ProductController::class, 'create'])->name('seller.products.create');
    Route::get('/seller/products/{product}', [ProductController::class, 'show'])->name('seller.products.show');
    Route::post('/seller/products', [ProductController::class, 'store'])->middleware('throttle:10,1')->name('seller.products.store');
    Route::get('/seller/products/{product}/edit', [ProductController::class, 'edit'])->name('seller.products.edit');
    Route::patch('/seller/products/{product}', [ProductController::class, 'update'])->middleware('throttle:10,1')->name('seller.products.update');
    Route::post('/seller/products/{product}/submit', [ProductController::class, 'submit'])->middleware('throttle:10,1')->name('seller.products.submit');
    Route::post('/seller/products/{product}/withdraw', [ProductController::class, 'withdraw'])->middleware('throttle:10,1')->name('seller.products.withdraw');
    Route::post('/seller/products/{product}/relist', [ProductController::class, 'relist'])->middleware('throttle:10,1')->name('seller.products.relist');
    Route::get('/seller/orders', [SellerOrderController::class, 'index'])->name('seller.orders.index');
    Route::get('/seller/returns', [SellerReturnCaseController::class, 'index'])->name('seller.returns.index');
    Route::get('/seller/returns/{returnCase}', [SellerReturnCaseController::class, 'show'])->name('seller.returns.show');
    Route::patch('/seller/returns/{returnCase}', [SellerReturnCaseController::class, 'update'])->middleware('throttle:10,1')->name('seller.returns.update');
    Route::post('/seller/orders/bulk-confirm', [SellerOrderController::class, 'bulkConfirm'])->middleware('throttle:10,1')->name('seller.orders.bulk-confirm');
    Route::get('/seller/orders/{sellerOrder}', [SellerOrderController::class, 'show'])->name('seller.orders.show');
    Route::patch('/seller/orders/{sellerOrder}', [SellerOrderController::class, 'update'])->middleware('throttle:10,1')->name('seller.orders.update');
    Route::post('/seller/orders/{sellerOrder}/failed-delivery', [SellerOrderController::class, 'reportFailedDelivery'])->middleware('throttle:10,1')->name('seller.orders.failed-delivery');
    Route::patch('/seller/orders/{sellerOrder}/cod-collection', [SellerOrderController::class, 'collectCod'])->middleware('throttle:10,1')->name('seller.orders.cod-collection');
    Route::get('/seller/earnings', [EarningController::class, 'index'])->name('seller.earnings.index');
    Route::get('/seller/platform-fees', [SellerPlatformFeeController::class, 'index'])->name('seller.platform-fees.index');
    Route::post('/seller/platform-fees/accept', [SellerPlatformFeeController::class, 'accept'])->middleware('throttle:10,1')->name('seller.platform-fees.accept');
    Route::post('/seller/platform-fees/{statement}/payment', [SellerPlatformFeeController::class, 'pay'])->middleware('throttle:10,1')->name('seller.platform-fees.pay');
    Route::get('/seller/platform-fees/{statement}/proof', [SellerPlatformFeeController::class, 'proof'])->name('seller.platform-fees.proof');
    Route::get('/seller/reviews', [SellerProductReviewController::class, 'index'])->name('seller.reviews.index');
    Route::post('/seller/reviews/{review}/response', [SellerProductReviewController::class, 'respond'])->middleware('throttle:10,1')->name('seller.reviews.respond');

});

Route::post('/seller/suspension-appeals', [SellerSuspensionAppealController::class, 'store'])
    ->middleware(['auth', 'clerk.verified', 'account:seller', 'throttle:5,1'])
    ->name('seller.suspension-appeals.store');

Route::middleware(['auth', 'clerk.verified', 'account:admin', 'platform-admin'])->group(function (): void {
    Route::get('/admin/chat-reports', [ChatReportController::class, 'index'])->name('admin.chat-reports.index');
    Route::patch('/admin/chat-reports/{report}', [ChatReportController::class, 'update'])->middleware('throttle:admin-mutations')->name('admin.chat-reports.update');
    Route::get('/admin/seller-applications', [AdminSellerApplicationController::class, 'index'])->name('admin.seller-applications.index');
    Route::get('/admin/seller-applications/{sellerApplication}', [AdminSellerApplicationController::class, 'show'])->name('admin.seller-applications.show');
    Route::post('/admin/seller-applications/{sellerApplication}/approve', [AdminSellerApplicationController::class, 'approve'])->middleware('throttle:admin-mutations')->name('admin.seller-applications.approve');
    Route::post('/admin/seller-applications/{sellerApplication}/reject', [AdminSellerApplicationController::class, 'reject'])->middleware('throttle:admin-mutations')->name('admin.seller-applications.reject');
    Route::post('/admin/seller-applications/{sellerApplication}/review', [AdminSellerApplicationController::class, 'review'])->middleware('throttle:admin-mutations')->name('admin.seller-applications.review');
    Route::post('/admin/seller-applications/{sellerApplication}/notes', [AdminSellerApplicationController::class, 'note'])->middleware('throttle:admin-mutations')->name('admin.seller-applications.note');
    Route::post('/admin/seller-applications/{sellerApplication}/request-information', [AdminSellerApplicationController::class, 'requestInformation'])->middleware('throttle:admin-mutations')->name('admin.seller-applications.request-information');
    Route::post('/admin/seller-applications/{sellerApplication}/suspend', [AdminSellerApplicationController::class, 'suspend'])->middleware('throttle:admin-mutations')->name('admin.seller-applications.suspend');
    Route::get('/admin/seller-applications/{sellerApplication}/documents/{document}', [AdminSellerApplicationController::class, 'document'])->scopeBindings()->name('admin.seller-applications.document');
    Route::get('/admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    Route::get('/admin/order-issues', [AdminOrderIssueController::class, 'index'])->name('admin.order-issues.index');
    Route::get('/admin/returns', [AdminReturnCaseController::class, 'index'])->name('admin.returns.index');
    Route::get('/admin/returns/{returnCase}', [AdminReturnCaseController::class, 'show'])->name('admin.returns.show');
    Route::post('/admin/seller-orders/{sellerOrder}/returns', [AdminReturnCaseController::class, 'store'])->middleware('throttle:admin-mutations')->name('admin.returns.store');
    Route::patch('/admin/returns/{returnCase}', [AdminReturnCaseController::class, 'update'])->middleware('throttle:admin-mutations')->name('admin.returns.update');
    Route::patch('/admin/order-issues/{orderIssue}', [AdminOrderIssueController::class, 'update'])->middleware('throttle:admin-mutations')->name('admin.order-issues.update');
    Route::patch('/admin/orders/{order}/payment/mark-paid', [AdminOrderController::class, 'markCodPaymentPaid'])->middleware('throttle:admin-mutations')->name('admin.orders.payment.mark-paid');
    Route::patch('/admin/seller-orders/{sellerOrder}/cod-collection', [AdminOrderController::class, 'markSellerCodCollected'])->middleware('throttle:admin-mutations')->name('admin.orders.seller-cod-collection');
    Route::get('/admin/payouts', [SellerPayoutController::class, 'index'])->name('admin.payouts.index');
    Route::post('/admin/payouts', [SellerPayoutController::class, 'store'])->middleware('throttle:admin-mutations')->name('admin.payouts.store');
    Route::get('/admin/platform-fees', [AdminPlatformFeeController::class, 'index'])->name('admin.platform-fees.index');
    Route::patch('/admin/platform-fees/{statement}', [AdminPlatformFeeController::class, 'update'])->middleware('throttle:admin-mutations')->name('admin.platform-fees.update');
    Route::get('/admin/platform-fees/{statement}/proof', [AdminPlatformFeeController::class, 'proof'])->name('admin.platform-fees.proof');
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
    Route::post('/admin/customers/{customer}/lift-cod-limit', [GovernanceController::class, 'liftCodLimit'])->middleware('throttle:admin-mutations')->name('admin.customers.lift-cod-limit');
    Route::get('/admin/audit-logs', [GovernanceController::class, 'auditLogs'])->name('admin.audit-logs.index');
    Route::get('/admin/announcements', [AnnouncementController::class, 'create'])->name('admin.announcements.create');
    Route::get('/admin/announcements/link-targets', [AnnouncementController::class, 'linkTargets'])->name('admin.announcements.link-targets');
    Route::post('/admin/announcements', [AnnouncementController::class, 'store'])->middleware('throttle:admin-mutations')->name('admin.announcements.store');
    Route::patch('/admin/announcements/{announcement}/cancel', [AnnouncementController::class, 'cancel'])->middleware('throttle:admin-mutations')->name('admin.announcements.cancel');
    Route::get('/admin/accounts', [AdminAccountController::class, 'index'])->name('admin.accounts.index');
    Route::post('/admin/accounts', [AdminAccountController::class, 'store'])->middleware('throttle:admin-mutations')->name('admin.accounts.store');
    Route::patch('/admin/accounts/{user}/deactivate', [AdminAccountController::class, 'deactivate'])->middleware('throttle:admin-mutations')->name('admin.accounts.deactivate');
    Route::get('/admin/reviews', [ProductReviewModerationController::class, 'index'])->name('admin.reviews.index');
    Route::patch('/admin/reviews/{review}', [ProductReviewModerationController::class, 'moderate'])->middleware('throttle:admin-mutations')->name('admin.reviews.moderate');
});

require __DIR__.'/auth.php';
