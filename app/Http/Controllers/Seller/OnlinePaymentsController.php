<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use App\Services\PayMongo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\RedirectResponse as ExternalRedirect;
use Throwable;

class OnlinePaymentsController extends Controller
{
    /**
     * Connects the store to PayMongo: creates its child account once, then sends the owner to
     * PayMongo's hosted ID check. Only the owner can, because this decides where the store's money goes.
     */
    public function store(Request $request, PayMongo $payMongo): RedirectResponse|ExternalRedirect
    {
        $store = $this->ownedStore($request);

        try {
            if ($store->paymongo_account_id === null) {
                $accountId = $payMongo->createChildAccount();
                DB::transaction(function () use ($request, $store, $accountId): void {
                    $store->update(['paymongo_account_id' => $accountId, 'paymongo_account_status' => 'pending']);
                    AuditLog::query()->create([
                        'actor_user_id' => $request->user()->id, 'action' => 'store.paymongo_connected', 'entity_type' => Store::class,
                        'entity_id' => $store->id, 'new_values' => ['paymongo_account_id' => $accountId], 'ip_address' => $request->ip(),
                    ]);
                });
            }

            return redirect()->away($payMongo->identityVerificationUrl($store->paymongo_account_id));
        } catch (Throwable $exception) {
            report($exception);

            return to_route('seller.store-settings.edit')->withErrors(['online_payments' => 'We couldn’t reach PayMongo. Please try again in a few minutes.']);
        }
    }

    /** PayMongo reviews the account on its side, so the owner checks back here for the result. */
    public function refresh(Request $request, PayMongo $payMongo): RedirectResponse
    {
        $store = $this->ownedStore($request);
        abort_if($store->paymongo_account_id === null, 404);

        try {
            $store->update(['paymongo_account_status' => $payMongo->accountStatus($store->paymongo_account_id)]);
        } catch (Throwable $exception) {
            report($exception);

            return to_route('seller.store-settings.edit')->withErrors(['online_payments' => 'We couldn’t reach PayMongo. Please try again in a few minutes.']);
        }

        return to_route('seller.store-settings.edit')->with('status', $store->acceptsOnlinePayments()
            ? 'Online payments are on. Buyers can now pay your store with GCash, Maya, or card.'
            : 'PayMongo is still reviewing your account. Check back later.');
    }

    private function ownedStore(Request $request): Store
    {
        $tenant = $request->user()->sellerTenant(['owner']);
        abort_unless($tenant?->store !== null, 403);

        return $tenant->store;
    }
}
