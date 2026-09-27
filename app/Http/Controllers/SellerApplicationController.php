<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellerApplicationRequest;
use App\Models\SellerApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SellerApplicationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (request()->user()->sellerTenant() !== null) {
            return to_route('seller.dashboard');
        }

        return view('seller-applications.create', [
            'application' => request()->user()->sellerApplications()->latest()->first(),
        ]);
    }

    public function store(StoreSellerApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();

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

        return redirect()->route('seller-application.create')->with('status', 'Seller application submitted for review.');
    }
}
