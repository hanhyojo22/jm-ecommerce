<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellerApplicationRequest;
use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SellerApplicationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $user = request()->user();

        if ($user->sellerTenant() !== null) {
            return to_route('seller.dashboard');
        }

        $membership = $user->tenantMemberships()->with('tenant.store')->oldest('created_at')->first();
        $store = $membership?->tenant?->store;
        $workspaceSuspended = $membership?->tenant?->status === 'suspended'
            || $store?->status === 'suspended';
        $suspensionAppeal = $membership?->role === 'owner' && $workspaceSuspended
            ? $store?->appeals()->latest()->first()
            : null;
        $suspensionReason = $workspaceSuspended && $store !== null
            ? AuditLog::query()
                ->where('action', 'store.suspended')
                ->where('entity_type', Store::class)
                ->where('entity_id', $store->id)
                ->latest('created_at')
                ->first()?->new_values['reason'] ?? null
            : null;

        return view('seller-applications.create', [
            'application' => $user->sellerApplications()->latest()->first(),
            'workspaceUnavailable' => $membership !== null,
            'workspaceSuspended' => $workspaceSuspended,
            'suspensionAppeal' => $suspensionAppeal,
            'suspensionReason' => $suspensionReason,
            'canSubmitSuspensionAppeal' => $membership?->role === 'owner'
                && $workspaceSuspended
                && $suspensionAppeal?->status !== 'pending',
        ]);
    }

    public function store(StoreSellerApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->sellerTenant() !== null) {
            return to_route('seller.dashboard');
        }

        if ($user->tenantMemberships()->exists()) {
            return to_route('seller-application.create');
        }

        if ($user->sellerApplications()->where('status', 'pending')->exists()) {
            return back()->withErrors(['application' => 'Your seller application is still under review.']);
        }

        $application = $user->sellerApplications()
            ->whereIn('status', ['draft', 'rejected'])
            ->latest()
            ->first() ?? new SellerApplication;

        $application->fill($request->validated());
        $application->status = 'pending';
        $application->submitted_at = now();
        $user->sellerApplications()->save($application);

        AuditLog::query()->create([
            'actor_user_id' => $user->id,
            'action' => 'seller_application.submitted',
            'entity_type' => SellerApplication::class,
            'entity_id' => $application->id,
            'new_values' => ['status' => 'pending'],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('seller-application.create')->with('status', 'Seller application submitted for review.');
    }
}
