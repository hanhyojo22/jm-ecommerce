<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MessageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ChatReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['report' => ['nullable', 'ulid']]);
        $reports = MessageReport::query()
            ->with(['message.sender', 'message.conversation.store', 'reporter'])
            ->when($filters['report'] ?? null, fn ($query, string $reportId) => $query->whereKey($reportId))
            ->latest()
            ->paginate(30);

        return view('admin.chat-reports.index', compact('reports'));
    }

    public function update(Request $request, MessageReport $report): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:reviewed,dismissed']]);
        $oldStatus = $report->status;
        $report->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'message_report.'.$data['status'],
            'entity_type' => MessageReport::class,
            'entity_id' => $report->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $data['status']],
            'ip_address' => $request->ip(),
        ]);
        Log::info('Chat report reviewed.', ['report_id' => $report->id, 'reviewer_id' => $request->user()->id, 'status' => $data['status']]);

        return back()->with('status', 'Report updated.');
    }
}
