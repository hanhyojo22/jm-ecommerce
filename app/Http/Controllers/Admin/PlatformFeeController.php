<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PlatformFeePayment;
use App\Http\Controllers\Controller;
use App\Models\PlatformFeeStatement;
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
            ->with('tenant.store', 'confirmedBy')
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

    public function update(Request $request, PlatformFeeStatement $statement, PlatformFeePayment $action): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $data = $request->validate([
            'action' => ['required', 'in:confirm,reject'],
            'note' => ['required_if:action,reject', 'nullable', 'string', 'min:10', 'max:1000'],
        ]);

        if ($data['action'] === 'confirm') {
            $action->confirm($request->user(), $statement, $request->ip());

            return back()->with('status', 'Payment confirmed.');
        }

        $action->reject($request->user(), $statement, $data['note'], $request->ip());

        return back()->with('status', 'Payment sent back to the store.');
    }

    public function proof(Request $request, PlatformFeeStatement $statement): StreamedResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        abort_unless($statement->payment_proof_path !== null && Storage::disk('chat_attachments')->exists($statement->payment_proof_path), 404);

        return Storage::disk('chat_attachments')->response($statement->payment_proof_path, $statement->payment_proof_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
