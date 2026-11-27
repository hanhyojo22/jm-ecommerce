<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\CaregiverController;
use App\Http\Controllers\Api\V1\CareProfileController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DeliveryAddressController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\DiscountCardController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MedicationConsentController;
use App\Http\Controllers\Api\V1\MedicationDoseController;
use App\Http\Controllers\Api\V1\MedicationHistoryController;
use App\Http\Controllers\Api\V1\MedicationPlanController;
use App\Http\Controllers\Api\V1\MessageAttachmentController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderIssueController;
use App\Http\Controllers\Api\V1\ProductDiscussionController;
use App\Http\Controllers\Api\V1\ProductReviewController;
use App\Http\Controllers\Api\V1\ReturnCaseController;
use App\Http\Controllers\Api\V1\ReturnCaseEvidenceController;
use App\Http\Controllers\Api\V1\SavedProductController;
use App\Http\Middleware\EnsureMedicationConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
 * Additive, versioned JSON surface for the mobile client. The Blade app keeps using
 * the web routes; both share the same Clerk guard, policies, actions, and queries.
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/me', AccountController::class)
        ->middleware(['auth', 'clerk.verified', 'throttle:api'])
        ->name('api.v1.me');
    Route::patch('/me', [AccountController::class, 'update'])
        ->middleware(['auth', 'clerk.verified', 'throttle:api'])
        ->name('api.v1.me.update');
    Route::delete('/me', [AccountController::class, 'destroy'])
        ->middleware(['auth', 'throttle:api'])
        ->name('api.v1.me.destroy');

    /*
     * The catalog is public, exactly like the storefront, so the app can show it
     * before anyone signs in. It still shares the public-visibility query.
     */
    Route::middleware('throttle:api')->group(function (): void {
        Route::get('/categories', [CatalogController::class, 'categories'])->name('api.v1.categories.index');
        Route::get('/products', [CatalogController::class, 'index'])->name('api.v1.products.index');
        Route::get('/products/suggestions', [CatalogController::class, 'suggestions'])->name('api.v1.products.suggestions');
        Route::get('/products/top', [CatalogController::class, 'top'])->name('api.v1.products.top');
        Route::get('/products/best-sellers', [CatalogController::class, 'bestSellers'])->name('api.v1.products.best-sellers');
        Route::get('/products/recommended', [CatalogController::class, 'recommended'])->name('api.v1.products.recommended');
        Route::get('/stores', [CatalogController::class, 'stores'])->name('api.v1.stores.index');
        Route::get('/store-locations', [CatalogController::class, 'storeLocations'])->name('api.v1.store-locations.index');
        Route::get('/locations', [LocationController::class, 'index'])->name('api.v1.locations.index');
        Route::get('/locations/barangays', [LocationController::class, 'barangays'])->name('api.v1.locations.barangays');
        Route::get('/stores/{store}', [CatalogController::class, 'store'])->name('api.v1.stores.show');
        Route::get('/stores/{store}/products/{product}', [CatalogController::class, 'show'])->name('api.v1.products.show');
        Route::get('/products/{product}/availability', [CatalogController::class, 'availability'])->name('api.v1.products.availability');
        Route::get('/stores/{store}/products/{product}/reviews', [CatalogController::class, 'reviews'])->name('api.v1.products.reviews');
        Route::get('/stores/{store}/products/{product}/discussions', [ProductDiscussionController::class, 'index'])->name('api.v1.products.discussions.index');
    });

    /*
     * The buyer's cart. Adding, changing and removing use the same actions as the
     * web cart, so stock and self-purchase rules stay in one place.
     */
    Route::middleware(['auth', 'clerk.verified', 'account:buyer,seller', 'throttle:cart'])
        ->prefix('cart')
        ->group(function (): void {
            Route::get('/', [CartController::class, 'show'])->name('api.v1.cart.show');
            Route::post('/items', [CartController::class, 'store'])->name('api.v1.cart.items.store');
            Route::post('/reorder/{order}', [CartController::class, 'reorder'])->name('api.v1.cart.reorder');
            Route::patch('/items/{cartItem}', [CartController::class, 'update'])->name('api.v1.cart.items.update');
            Route::delete('/items/{cartItem}', [CartController::class, 'destroy'])->name('api.v1.cart.items.destroy');
            Route::patch('/selection', [CartController::class, 'updateSelection'])->name('api.v1.cart.selection.update');
            Route::patch('/branch', [CartController::class, 'updateBranch'])->name('api.v1.cart.branch.update');
            Route::delete('/selected', [CartController::class, 'destroySelected'])->name('api.v1.cart.selected.destroy');
        });

    /*
     * Checkout, saved addresses and the buyer's own purchases.
     */
    Route::middleware(['auth', 'clerk.verified', 'account:buyer,seller'])->group(function (): void {
        Route::get('/checkout', [CheckoutController::class, 'show'])
            ->middleware('throttle:api')
            ->name('api.v1.checkout.show');
        Route::post('/checkout', [CheckoutController::class, 'store'])
            ->middleware('throttle:checkout')
            ->name('api.v1.checkout.store');

        /*
         * Medicine reminders. Opting in is the only call allowed before consent.
         */
        Route::middleware('throttle:api')->group(function (): void {
            Route::put('/me/medication-consent', [MedicationConsentController::class, 'store'])->name('api.v1.medication-consent.store');
            Route::delete('/me/medication-consent', [MedicationConsentController::class, 'destroy'])->name('api.v1.medication-consent.destroy');
            // Caregivers accept and leave without opting in themselves; inviting one needs the patient's consent.
            Route::get('/caregivers', [CaregiverController::class, 'index'])->name('api.v1.caregivers.index');
            Route::post('/caregivers', [CaregiverController::class, 'store'])->middleware('throttle:10,1')->name('api.v1.caregivers.store');
            Route::post('/caregivers/{link}/accept', [CaregiverController::class, 'accept'])->name('api.v1.caregivers.accept');
            Route::delete('/caregivers/{link}', [CaregiverController::class, 'destroy'])->name('api.v1.caregivers.destroy');

            Route::middleware(EnsureMedicationConsent::class)->group(function (): void {
                Route::get('/medication-plans', [MedicationPlanController::class, 'index'])->name('api.v1.medication-plans.index');
                Route::get('/medication-plans/suggestions', [MedicationPlanController::class, 'suggestions'])->name('api.v1.medication-plans.suggestions');
                Route::post('/medication-plans', [MedicationPlanController::class, 'store'])->name('api.v1.medication-plans.store');
                Route::patch('/medication-plans/{plan}', [MedicationPlanController::class, 'update'])->name('api.v1.medication-plans.update');
                Route::delete('/medication-plans/{plan}', [MedicationPlanController::class, 'destroy'])->name('api.v1.medication-plans.destroy');
                Route::post('/medication-plans/{plan}/count', [MedicationPlanController::class, 'count'])->name('api.v1.medication-plans.count');
                Route::post('/medication-plans/{plan}/doses', [MedicationDoseController::class, 'store'])->name('api.v1.medication-plans.doses.store');
                Route::get('/medication-history', MedicationHistoryController::class)->name('api.v1.medication-history');
                Route::get('/care-profiles', [CareProfileController::class, 'index'])->name('api.v1.care-profiles.index');
                Route::post('/care-profiles', [CareProfileController::class, 'store'])->name('api.v1.care-profiles.store');
                Route::patch('/care-profiles/{careProfile}', [CareProfileController::class, 'update'])->name('api.v1.care-profiles.update');
                Route::delete('/care-profiles/{careProfile}', [CareProfileController::class, 'destroy'])->name('api.v1.care-profiles.destroy');
            });
        });

        Route::get('/delivery-addresses', [DeliveryAddressController::class, 'index'])
            ->middleware('throttle:api')
            ->name('api.v1.delivery-addresses.index');
        Route::post('/delivery-addresses', [DeliveryAddressController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('api.v1.delivery-addresses.store');
        Route::patch('/delivery-addresses/{deliveryAddress}', [DeliveryAddressController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('api.v1.delivery-addresses.update');
        Route::delete('/delivery-addresses/{deliveryAddress}', [DeliveryAddressController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('api.v1.delivery-addresses.destroy');

        Route::get('/discount-cards', [DiscountCardController::class, 'index'])
            ->middleware('throttle:api')
            ->name('api.v1.discount-cards.index');
        Route::delete('/discount-cards/{discountCard}', [DiscountCardController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('api.v1.discount-cards.destroy');

        Route::get('/saved-products', [SavedProductController::class, 'index'])
            ->middleware('throttle:api')
            ->name('api.v1.saved-products.index');
        Route::post('/saved-products/{product}', [SavedProductController::class, 'store'])
            ->middleware('throttle:cart')
            ->name('api.v1.saved-products.store');
        Route::delete('/saved-products/{product}', [SavedProductController::class, 'destroy'])
            ->middleware('throttle:cart')
            ->name('api.v1.saved-products.destroy');
        Route::get('/products/bought-before', [CatalogController::class, 'boughtBefore'])
            ->middleware('throttle:api')
            ->name('api.v1.products.bought-before');

        Route::get('/orders', [OrderController::class, 'index'])
            ->middleware('throttle:api')
            ->name('api.v1.orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])
            ->middleware('throttle:api')
            ->name('api.v1.orders.show');
        Route::post('/orders/{order}/pay', [OrderController::class, 'pay'])
            ->middleware('throttle:10,1')
            ->name('api.v1.orders.pay');
        Route::post('/orders/{order}/prescription', [OrderController::class, 'resubmitPrescription'])
            ->middleware('throttle:10,1')
            ->name('api.v1.orders.prescription.store');
        Route::post('/orders/seller-orders/{sellerOrder}/received', [OrderController::class, 'received'])
            ->middleware('throttle:10,1')
            ->name('api.v1.orders.received');

        Route::get('/orders/seller-orders/{sellerOrder}/issues', [OrderIssueController::class, 'index'])
            ->middleware('throttle:api')
            ->name('api.v1.order-issues.index');
        Route::post('/orders/seller-orders/{sellerOrder}/issues', [OrderIssueController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('api.v1.order-issues.store');

        Route::post('/orders/seller-orders/{sellerOrder}/returns', [ReturnCaseController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('api.v1.returns.store');
        Route::get('/returns/{returnCase}', [ReturnCaseController::class, 'show'])
            ->middleware('throttle:api')
            ->name('api.v1.returns.show');
        Route::patch('/returns/{returnCase}', [ReturnCaseController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('api.v1.returns.update');

        Route::post('/order-items/{orderItem}/review', [ProductReviewController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('api.v1.reviews.store');
        Route::patch('/reviews/{review}', [ProductReviewController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('api.v1.reviews.update');
        Route::delete('/reviews/{review}', [ProductReviewController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('api.v1.reviews.destroy');
    });

    /*
     * Buyer chat. Message sends are idempotent by client_message_id and rate-limited
     * inside the controller, matching the web thread.
     */
    Route::middleware(['auth', 'clerk.verified', 'account:buyer,seller'])->group(function (): void {
        Route::post('/stores/{store}/products/{product}/discussions', [ProductDiscussionController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('api.v1.products.discussions.store');
        Route::get('/products/{product}/review-eligibility', [ProductReviewController::class, 'eligibility'])
            ->middleware('throttle:api')
            ->name('api.v1.products.review-eligibility');
        Route::get('/conversations', [ConversationController::class, 'index'])
            ->middleware('throttle:api')
            ->name('api.v1.conversations.index');
        Route::post('/conversations/start', [ConversationController::class, 'start'])
            ->middleware('throttle:10,1')
            ->name('api.v1.conversations.start');
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])
            ->middleware('throttle:api')
            ->name('api.v1.conversations.show');
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages'])
            ->middleware('throttle:api')
            ->name('api.v1.conversations.messages.index');
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send'])
            ->name('api.v1.conversations.messages.store');
        Route::post('/conversations/{conversation}/read', [ConversationController::class, 'read'])
            ->middleware('throttle:conversation-read')
            ->name('api.v1.conversations.read');
        Route::post('/conversations/{conversation}/typing', [ConversationController::class, 'typing'])
            ->middleware('throttle:60,1')
            ->name('api.v1.conversations.typing');
        Route::post('/conversations/{conversation}/attachments', [ConversationController::class, 'attach'])
            ->middleware('throttle:10,1')
            ->name('api.v1.conversations.attachments.store');
        Route::post('/conversations/{conversation}/messages/{message}/report', [ConversationController::class, 'report'])
            ->middleware('throttle:10,1')
            ->name('api.v1.conversations.messages.report');

        Route::post('/device-tokens', [DeviceTokenController::class, 'store'])
            ->middleware('throttle:api')
            ->name('api.v1.device-tokens.store');
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy'])
            ->middleware('throttle:api')
            ->name('api.v1.device-tokens.destroy');
    });

    /*
     * Reverb/Pusher private-channel authentication for the bearer-token client.
     */
    Route::post('/broadcasting/auth', fn (Request $request) => Broadcast::auth($request))
        ->middleware(['auth', 'throttle:api'])
        ->name('api.v1.broadcasting.auth');

    /*
     * Private chat media is served through a short-lived signed URL that the message
     * payload carries, so an image widget does not have to send a bearer token.
     */
    Route::get('/media/attachments/{attachment}', [MessageAttachmentController::class, 'show'])
        ->middleware(['signed', 'throttle:api'])
        ->name('api.v1.media.attachments.show');

    Route::get('/media/return-evidence/{evidence}', [ReturnCaseEvidenceController::class, 'show'])
        ->middleware(['signed', 'throttle:api'])
        ->name('api.v1.media.return-evidence.show');
});
