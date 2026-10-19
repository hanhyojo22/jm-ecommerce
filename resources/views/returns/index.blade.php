<x-app-layout>
    <div class="page-shell"><div class="page-container py-8">
        <h1 class="text-3xl font-bold text-ink">{{ $role === 'seller' ? 'Seller returns' : 'Return cases' }}</h1>
        <p class="mt-2 text-sm text-muted">{{ $needsActionCount }} need {{ $role === 'seller' ? 'seller action' : 'support review' }} · {{ $cases->total() }} {{ $needsActionOnly ? 'shown' : 'total' }}. Review each case and its next action.</p>
        <p class="mt-3">
            <a href="{{ route($role === 'seller' ? 'seller.returns.index' : 'admin.returns.index', $needsActionOnly ? [] : ['needs_action' => 1]) }}" class="inline-flex min-h-10 items-center rounded-lg border border-line px-3 text-sm font-semibold text-brand">
                {{ $needsActionOnly ? 'Show all cases' : 'Show only cases needing action' }}
            </a>
        </p>
        <div class="mt-6 overflow-hidden rounded-lg border border-line bg-surface">
            @forelse ($cases as $case)
                @php($deadline = $case->deadline())
                <a href="{{ route($role === 'seller' ? 'seller.returns.show' : 'admin.returns.show', $case) }}" class="block border-b border-line p-4 hover:bg-canvas">
                    <span class="font-semibold text-brand">{{ $case->sellerOrder->order->number }} · {{ $case->sellerOrder->store->name }}</span>
                    <span class="ml-2 text-sm text-ink">{{ str($case->status)->replace('_', ' ')->headline() }}</span>
                    @if ($case->isOverdue())<span class="ml-2 text-sm font-semibold text-danger">Overdue</span>@endif
                    <p class="mt-1 text-sm text-muted">
                        {{ ucfirst(str_replace('_', ' ', $case->reason)) }} · {{ $case->buyer->name }} · opened {{ $case->created_at->format('M j, Y') }}@if ($deadline) · due {{ $deadline->format('M j, g:i A') }}@endif
                    </p>
                </a>
            @empty
                <p class="p-6 text-muted">{{ $needsActionOnly ? 'No cases are waiting on you.' : 'No return cases yet.' }}</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $cases->links() }}</div>
    </div></div>
</x-app-layout>
