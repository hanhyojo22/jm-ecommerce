<?php

namespace App\Http\Controllers\Seller;

use App\Actions\AcceptPlatformFeeTerms;
use App\Actions\PlatformFeePayment;
use App\Http\Controllers\Controller;
use App\Models\PlatformFeeStatement;
use App\Models\SellerEarning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformFeeController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->user()->sellerTenant() ?? abort(403);

        return view('seller.platform-fees.index', [
            'tenant' => $tenant,
            'isOwner' => $request->user()->sellerTenant(['owner'])?->is($tenant) ?? false,
            'statements' => PlatformFeeStatement::query()->where('tenant_id', $tenant->id)->latest('period_start')->paginate(12),
            'unbilledTotal' => SellerEarning::query()->where('tenant_id', $tenant->id)->whereNull('platform_fee_statement_id')->sum('commission_amount'),
            'paymentInstructions' => config('marketplace.platform_fee.payment_instructions'),
        ]);
    }

    public function accept(Request $request, AcceptPlatformFeeTerms $action): RedirectResponse
    {
        $tenant = $request->user()->sellerTenant(['owner']) ?? abort(403);
        $request->validate(['accept' => ['accepted']], ['accept.accepted' => 'Tick the box to accept the Marketplace Fee Policy.']);

        $action->handle($request->user(), $tenant, $request->ip(), $request->userAgent());

        return back()->with('status', 'Marketplace Fee Policy accepted. Fees apply to orders delivered from today.');
    }

    public function pay(Request $request, PlatformFeeStatement $statement, PlatformFeePayment $action): RedirectResponse
    {
        $tenant = $request->user()->sellerTenant(['owner']) ?? abort(403);
        abort_unless($statement->tenant_id === $tenant->id, 404);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $action->submit($request->user(), $statement, $data['reference'], $data['proof'], $request->ip());

        return back()->with('status', 'Payment submitted. JMMart will confirm it shortly.');
    }

    public function proof(Request $request, PlatformFeeStatement $statement): StreamedResponse
    {
        $tenant = $request->user()->sellerTenant() ?? abort(403);
        abort_unless($statement->tenant_id === $tenant->id && $statement->payment_proof_path !== null, 404);
        abort_unless(Storage::disk('chat_attachments')->exists($statement->payment_proof_path), 404);

        return Storage::disk('chat_attachments')->response($statement->payment_proof_path, $statement->payment_proof_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
