<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold uppercase tracking-[.16em] text-emerald-700">Marketplace governance</p><h1 class="mt-1 text-2xl font-semibold text-slate-950">{{ ($filters['status'] ?? null) === 'pending' ? 'Pending chat reports' : 'Chat reports' }}</h1></div></x-slot>
    <div class="page-shell"><div class="page-container space-y-4">
        @forelse ($reports as $report)
            <article id="chat-report-{{ $report->id }}" class="section-card scroll-mt-24">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-semibold">{{ $report->reason }} · {{ $report->status }}</p><p class="mt-1 text-xs text-slate-500">Reported by {{ $report->reporter->name }} · {{ $report->created_at->diffForHumans() }}</p></div><p class="text-xs text-slate-500">Conversation {{ $report->message->conversation_id }}</p></div>
                <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $report->message->body }}</p>
                @if ($report->details)<p class="mt-2 text-sm text-slate-600">{{ $report->details }}</p>@endif
                @if ($report->status === 'pending')<form method="POST" action="{{ route('admin.chat-reports.update', $report) }}" class="mt-4 flex gap-2">@csrf @method('PATCH')<button name="status" value="reviewed" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Mark reviewed</button><button name="status" value="dismissed" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Dismiss</button></form>@endif
            </article>
        @empty
            <div class="empty-state content-card">No chat reports are waiting for review.</div>
        @endforelse
        {{ $reports->links() }}
    </div></div>
</x-app-layout>
