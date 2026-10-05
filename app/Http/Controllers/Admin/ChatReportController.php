<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ChatReportController extends Controller
{
    public function index(): View
    {
        $reports = MessageReport::query()
            ->with(['message.sender', 'message.conversation.store', 'reporter'])
            ->latest()
            ->paginate(30);

        return view('admin.chat-reports.index', compact('reports'));
    }

    public function update(Request $request, MessageReport $report): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:reviewed,dismissed']]);
        $report->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        Log::info('Chat report reviewed.', ['report_id' => $report->id, 'reviewer_id' => $request->user()->id, 'status' => $data['status']]);

        return back()->with('status', 'Report updated.');
    }
}
