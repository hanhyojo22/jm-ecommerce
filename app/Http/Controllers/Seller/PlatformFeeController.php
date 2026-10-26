<?php

namespace App\Http\Controllers\Seller;

use App\Actions\AcceptPlatformFeeTerms;
use App\Actions\IssuePlatformFeeStatements;
use App\Actions\PlatformFeePayment;
use App\Http\Controllers\Controller;
use App\Models\PlatformFeeStatement;
use App\Models\User;
use App\Services\GmailOrderMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformFeeController extends Controller
{
    public function index(Request $request, IssuePlatformFeeStatements $statements): View
    {
        $tenant = $request->user()->sellerTenant() ?? abort(403);

        return view('seller.platform-fees.index', [
            'tenant' => $tenant,
            'isOwner' => $request->user()->sellerTenant(['owner'])?->is($tenant) ?? false,
            'statements' => PlatformFeeStatement::query()->where('tenant_id', $tenant->id)->with('earnings.sellerOrder.order')->latest('period_start')->paginate(12),
            'openStatements' => PlatformFeeStatement::query()->where('tenant_id', $tenant->id)->whereIn('status', ['unpaid', 'submitted'])->where('amount_due', '>', 0)->orderBy('due_at')->get(),
            'preview' => $statements->preview($tenant->id),
            'paymentInstructions' => config('marketplace.platform_fee.payment_instructions'),
        ]);
    }

    public function accept(Request $request, AcceptPlatformFeeTerms $action): RedirectResponse
    {
        $tenant = $request->user()->sellerTenant(['owner']) ?? abort(403);
        $request->validate(['accept' => ['accepted']], ['accept.accepted' => 'Tick the box to accept the Marketplace Fee Policy.']);

        $wasCharged = $tenant->platform_fee_accepted_at !== null;
        $action->handle($request->user(), $tenant, $request->ip(), $request->userAgent());

        return back()->with('status', $wasCharged
            ? 'Updated Marketplace Fee Policy accepted. The new rate applies to orders delivered from today.'
            : 'Marketplace Fee Policy accepted. Fees apply to orders delivered from today.');
    }

    public function pay(Request $request, PlatformFeeStatement $statement, PlatformFeePayment $action, GmailOrderMailer $mailer): RedirectResponse
    {
        $tenant = $request->user()->sellerTenant(['owner']) ?? abort(403);
        abort_unless($statement->tenant_id === $tenant->id, 404);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $action->submit($request->user(), $statement, $data['reference'], $data['proof'], $request->ip());

        foreach (User::query()->where('account_type', 'admin')->whereIn('platform_role', ['super_admin', 'admin'])->get() as $administrator) {
            try {
                $mailer->send(
                    $administrator,
                    'JMMart platform fee payment to confirm',
                    ($tenant->store?->name ?? $tenant->name).' submitted ₱'.number_format((float) $statement->amount_due, 2)." for {$statement->period_start->format('F Y')}, reference {$data['reference']}."
                    ."\n\nCheck it against the account: ".route('admin.platform-fees.index', ['status' => 'submitted']),
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

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
