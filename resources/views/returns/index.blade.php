<x-app-layout>
    <div class="page-shell"><div class="page-container py-8">
        <h1 class="text-3xl font-bold text-ink">{{ $role === 'seller' ? 'Seller returns' : 'Return cases' }}</h1>
        <p class="mt-2 text-sm text-muted">{{ $needsActionCount }} need {{ $role === 'seller' ? 'seller action' : 'support review' }} · {{ $cases->total() }} total. Review each case and its next action.</p>
        <div class="mt-6 overflow-hidden rounded-lg border border-line bg-surface">
            @forelse ($cases as $case)
                <a href="{{ route($role === 'seller' ? 'seller.returns.show' : 'admin.returns.show', $case) }}" class="block border-b border-line p-4 hover:bg-canvas">
                    <span class="font-semibold text-brand">{{ $case->sellerOrder->order->number }} · {{ $case->sellerOrder->store->name }}</span>
                    <span class="ml-2 text-sm text-ink">{{ str($case->status)->replace('_', ' ')->headline() }}</span>
                    @if ($case->status === 'awaiting_seller' && $case->respond_by?->isPast())<span class="ml-2 text-sm font-semibold text-danger">Overdue</span>@endif
                    <p class="mt-1 text-sm text-muted">{{ ucfirst(str_replace('_', ' ', $case->reason)) }} · opened {{ $case->created_at->format('M j, Y') }}</p>
                </a>
            @empty
                <p class="p-6 text-muted">No return cases yet.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $cases->links() }}</div>
    </div></div>
</x-app-layout>
