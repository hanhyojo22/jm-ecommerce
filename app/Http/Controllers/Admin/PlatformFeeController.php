<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PlatformFeePayment;
use App\Http\Controllers\Controller;
use App\Models\PlatformFeeStatement;
use App\Services\GmailOrderMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformFeeController extends Controller
{
    /** @var array<int, string> */
    private const FILTERS = ['submitted', 'overdue', 'unpaid', 'paid'];

    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : '';

        $statements = PlatformFeeStatement::query()
            ->when($filter === 'overdue', fn ($query) => $query->overdue())
            ->when(in_array($filter, ['submitted', 'unpaid', 'paid'], true), fn ($query) => $query->where('status', $filter))
            ->with('tenant.store', 'confirmedBy', 'earnings.sellerOrder.order')
            // Payments waiting for review come first, then what is overdue.
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 WHEN status = 'unpaid' AND due_at < ? THEN 1 WHEN status = 'unpaid' THEN 2 ELSE 3 END", [now()])
            ->latest('period_start')
            ->paginate(25)
            ->withQueryString();

        return view('admin.platform-fees.index', [
            'statements' => $statements,
            'filter' => $filter,
            'submittedCount' => PlatformFeeStatement::query()->where('status', 'submitted')->count(),
            'overdueCount' => PlatformFeeStatement::query()->overdue()->count(),
            'outstandingTotal' => PlatformFeeStatement::query()->whereIn('status', ['unpaid', 'submitted'])->sum('amount_due'),
        ]);
    }

    public function update(Request $request, PlatformFeeStatement $statement, PlatformFeePayment $action, GmailOrderMailer $mailer): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $data = $request->validate([
            'action' => ['required', 'in:confirm,reject,mark_paid'],
            // A mark-paid note becomes the statement's payment reference, a 255-character column.
            'note' => ['exclude_unless:action,reject,mark_paid', 'required', 'string', 'min:10', $request->input('action') === 'mark_paid' ? 'max:255' : 'max:1000'],
        ]);

        $month = $statement->period_start->format('F Y');

        if ($data['action'] === 'mark_paid') {
            $action->markPaid($request->user(), $statement, $data['note'], $request->ip());
            $this->notifyStore($statement, $mailer, "JMMart settled your {$month} platform fee", "JMMart marked your {$month} platform fee of ₱".number_format((float) $statement->amount_due, 2)." as settled: {$data['note']}");

            return back()->with('status', 'Statement marked paid.');
        }

        if ($data['action'] === 'confirm') {
            $action->confirm($request->user(), $statement, $request->ip());
            $this->notifyStore($statement, $mailer, "JMMart confirmed your {$month} platform fee payment", 'Your payment of ₱'.number_format((float) $statement->amount_due, 2)." for {$month} is confirmed. Thank you.");

            return back()->with('status', 'Payment confirmed.');
        }

        $action->reject($request->user(), $statement, $data['note'], $request->ip());
        $this->notifyStore($statement, $mailer, "JMMart could not confirm your {$month} platform fee payment", "JMMart could not confirm your payment for {$month}: {$data['note']}\n\nPlease submit it again by {$statement->due_at->format('M j, Y')}.");

        return back()->with('status', 'Payment sent back to the store.');
    }

    private function notifyStore(PlatformFeeStatement $statement, GmailOrderMailer $mailer, string $subject, string $body): void
    {
        foreach ($statement->tenant->members()->with('user')->get()->pluck('user')->filter() as $recipient) {
            try {
                $mailer->send($recipient, $subject, $body."\n\nView your statements: ".route('seller.platform-fees.index'));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function proof(Request $request, PlatformFeeStatement $statement): StreamedResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        abort_unless($statement->payment_proof_path !== null && Storage::disk('chat_attachments')->exists($statement->payment_proof_path), 404);

        return Storage::disk('chat_attachments')->response($statement->payment_proof_path, $statement->payment_proof_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
