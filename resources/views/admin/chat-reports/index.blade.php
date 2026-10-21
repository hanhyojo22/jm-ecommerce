<x-app-layout>
    <x-slot name="header"><div><h1 class="mt-1 marketplace-page-title">{{ ($filters['status'] ?? null) === 'pending' ? 'Pending chat reports' : 'Chat reports' }}</h1></div></x-slot>
    <div class="page-shell"><div class="page-container space-y-4">
        @forelse ($reports as $report)
            <article id="chat-report-{{ $report->id }}" class="section-card scroll-mt-24">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-semibold">{{ $report->reason }} · {{ $report->status }}</p><p class="mt-1 text-xs text-muted">Reported by {{ $report->reporter->name }} · {{ $report->created_at->diffForHumans() }}</p></div><p class="text-xs text-muted">Conversation {{ $report->message->conversation_id }}</p></div>
                <p class="mt-3 rounded-lg bg-canvas p-3 text-sm whitespace-pre-line">{{ $report->message->body }}</p>
                @if ($report->details)<p class="mt-2 text-sm text-muted">{{ $report->details }}</p>@endif
                @if ($report->status === 'pending')<form method="POST" action="{{ route('admin.chat-reports.update', $report) }}" class="mt-4 flex gap-2">@csrf @method('PATCH')<button name="status" value="reviewed" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white transition-colors hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Mark reviewed</button><button name="status" value="dismissed" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold text-ink transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Dismiss</button></form>@endif
            </article>
        @empty
            <div class="empty-state content-card">No chat reports are waiting for review.</div>
        @endforelse
        {{ $reports->links() }}
    </div></div>
</x-app-layout>
