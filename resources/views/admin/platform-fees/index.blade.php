<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="mt-1 marketplace-page-title">Platform fees</h1>
            <p class="mt-2 text-sm text-muted">Monthly statements for the {{ rtrim(rtrim(bcmul((string) config('marketplace.platform_fee.rate'), '100', 2), '0'), '.') }}% fee on delivered orders. Confirm payments stores submit.</p>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container">
            @if (session('status'))<div class="mb-5 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="mb-5 rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm font-medium text-danger" role="alert">{{ $errors->first() }}</div>@endif

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Platform fee summary">
                <article class="section-card"><p class="text-sm font-medium text-muted">Outstanding</p><p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format((float) $outstandingTotal, 2) }}</p><p class="mt-2 text-sm text-muted">Unpaid and awaiting confirmation.</p></article>
                <article class="section-card"><p class="text-sm font-medium text-muted">Awaiting confirmation</p><p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-ink">{{ $submittedCount }}</p><p class="mt-2 text-sm text-muted">Payments to check against your account.</p></article>
                <article class="section-card"><p class="text-sm font-medium text-muted">Overdue</p><p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight {{ $overdueCount > 0 ? 'text-danger' : 'text-ink' }}">{{ $overdueCount }}</p><p class="mt-2 text-sm text-muted">Past due and still unpaid.</p></article>
            </section>

            <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filter statements">
                @foreach (['' => 'All', 'submitted' => 'Awaiting confirmation', 'overdue' => 'Overdue', 'unpaid' => 'Unpaid', 'paid' => 'Paid'] as $value => $label)
                    <a href="{{ route('admin.platform-fees.index', array_filter(['status' => $value])) }}" @if ($filter === $value) aria-current="page" @endif class="inline-flex min-h-10 items-center rounded-lg border px-3 text-sm font-semibold {{ $filter === $value ? 'border-ink bg-ink text-white' : 'border-line bg-white text-ink hover:border-brand hover:text-brand' }}">{{ $label }}</a>
                @endforeach
            </nav>

            <section class="mt-4 content-card" aria-label="Statements">
                <div class="divide-y divide-line">
                    @forelse ($statements as $statement)
                        <article class="p-5 sm:px-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <h2 class="font-semibold text-ink">
                                        @if ($statement->tenant?->store)<a href="{{ route('admin.stores.show', $statement->tenant->store) }}" class="hover:text-brand">{{ $statement->tenant->store->name }}</a>@else{{ $statement->tenant?->name }}@endif
                                        <span class="font-normal text-muted">· {{ $statement->period_start->format('F Y') }}</span>
                                    </h2>
                                    <p class="mt-1 text-sm text-muted">₱{{ number_format((float) $statement->gross_total, 2) }} delivered · ₱{{ number_format((float) $statement->commission_total, 2) }} fees · −₱{{ number_format(bcadd((string) $statement->refund_credit, (string) $statement->credit_carried_in, 2), 2) }} credits · due {{ $statement->due_at->format('M j, Y') }}</p>
                                    @if ($statement->payment_reference)<p class="mt-1 text-sm text-ink">Reference <span class="font-semibold">{{ $statement->payment_reference }}</span>@if ($statement->payment_submitted_at) · submitted {{ $statement->payment_submitted_at->format('M j, Y g:i A') }}@endif @if ($statement->payment_proof_path) · <a href="{{ route('admin.platform-fees.proof', $statement) }}" target="_blank" rel="noopener" class="font-semibold text-brand underline">View receipt</a>@endif</p>@endif
                                    @if ($statement->status === 'paid' && $statement->confirmedBy)<p class="mt-1 text-sm text-muted">Confirmed by {{ $statement->confirmedBy->name }} on {{ $statement->paid_at?->format('M j, Y') }}</p>@endif
                                    @if ($statement->review_note && $statement->status === 'unpaid')<p class="mt-1 text-sm text-danger">Sent back: {{ $statement->review_note }}</p>@endif
                                    @if ($statement->isOverdue())
                                        @php($daysOverdue = (int) $statement->due_at->diffInDays(now()))
                                        <p class="mt-1 text-sm font-semibold text-danger">Overdue {{ trans_choice('{0} less than a day|{1} :count day|[2,*] :count days', $daysOverdue) }}.@if ($daysOverdue >= 30 && $statement->tenant?->store && $statement->tenant->store->status !== 'suspended') <a href="{{ route('admin.stores.show', $statement->tenant->store) }}" class="underline">Review the store for suspension</a>@endif</p>
                                    @endif
                                    @if ($statement->earnings->isNotEmpty())
                                        <details class="mt-2 text-sm">
                                            <summary class="inline-flex min-h-10 cursor-pointer items-center font-semibold text-brand hover:text-brand-hover">{{ trans_choice('{1} :count order|[2,*] :count orders', $statement->earnings->count()) }} on this statement</summary>
                                            <ul class="mt-1 max-h-60 divide-y divide-line overflow-y-auto rounded-lg border border-line">
                                                @foreach ($statement->earnings->sortBy('available_at') as $earning)
                                                    <li class="flex items-center justify-between gap-4 px-3 py-2"><span class="min-w-0 truncate">@if ($earning->sellerOrder?->order)<a href="{{ route('admin.orders.show', $earning->sellerOrder->order) }}" class="text-brand-hover underline">#{{ $earning->sellerOrder->order->number }}</a>@endif <span class="text-muted">delivered {{ $earning->available_at->format('M j') }}</span></span><span class="shrink-0 tabular-nums text-ink">₱{{ number_format((float) $earning->commission_amount, 2) }} on ₱{{ number_format((float) $earning->gross_amount, 2) }}</span></li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </div>
                                <div class="shrink-0 sm:text-right">
                                    <p class="text-xl font-semibold tabular-nums text-ink">₱{{ number_format((float) $statement->amount_due, 2) }}</p>
                                    <span class="status-badge mt-1 {{ match (true) { $statement->status === 'paid' => 'bg-success/10 text-success ring-success/20', $statement->status === 'submitted' => 'bg-blue-50 text-brand-hover ring-blue-200', $statement->isOverdue() => 'bg-danger/10 text-danger ring-danger/20', default => 'bg-amber-50 text-amber-800 ring-amber-200' } }}">{{ match (true) { (float) $statement->amount_due === 0.0 => 'Nothing due', $statement->status === 'paid' => 'Paid', $statement->status === 'submitted' => 'Awaiting confirmation', $statement->isOverdue() => 'Overdue', default => 'Unpaid' } }}</span>
                                </div>
                            </div>

                            @if ($statement->status === 'submitted')
                                <div x-data="{ rejecting: false }" class="mt-4 rounded-lg bg-canvas p-4">
                                    <div class="flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('admin.platform-fees.update', $statement) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="confirm"><button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Confirm payment received</button></form>
                                        <button type="button" @click="rejecting = ! rejecting" :aria-expanded="rejecting.toString()" class="inline-flex min-h-11 items-center rounded-lg border border-line bg-white px-4 text-sm font-semibold text-ink hover:border-danger hover:text-danger">Send back</button>
                                    </div>
                                    <form x-cloak x-show="rejecting" method="POST" action="{{ route('admin.platform-fees.update', $statement) }}" class="mt-3 grid gap-2">@csrf @method('PATCH')<input type="hidden" name="action" value="reject">
                                        <label for="note-{{ $statement->id }}" class="field-label">What should the store fix?</label>
                                        <textarea id="note-{{ $statement->id }}" name="note" rows="2" required minlength="10" maxlength="1000" class="block w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand"></textarea>
                                        <button type="submit" class="inline-flex min-h-11 items-center justify-self-start rounded-lg bg-danger px-4 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2">Send back to store</button>
                                    </form>
                                </div>
                            @elseif ($statement->status === 'unpaid' && (float) $statement->amount_due > 0)
                                <details class="mt-4 rounded-lg bg-canvas p-4">
                                    <summary class="inline-flex min-h-10 cursor-pointer items-center text-sm font-semibold text-ink">Paid another way or waived?</summary>
                                    <form method="POST" action="{{ route('admin.platform-fees.update', $statement) }}" class="mt-3 grid gap-2">@csrf @method('PATCH')<input type="hidden" name="action" value="mark_paid">
                                        <label for="mark-paid-{{ $statement->id }}" class="field-label">How was it settled? The store sees this.</label>
                                        <input id="mark-paid-{{ $statement->id }}" name="note" required minlength="10" maxlength="255" placeholder="For example: paid ₱{{ number_format((float) $statement->amount_due, 2) }} cash at the office, OR #1234" class="block w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand">
                                        <button type="submit" class="inline-flex min-h-11 items-center justify-self-start rounded-lg border border-line bg-white px-4 text-sm font-semibold text-ink hover:border-brand hover:text-brand">Mark paid</button>
                                    </form>
                                </details>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state"><p class="font-semibold text-ink">No statements here</p><p class="mt-2 text-sm text-muted">Statements are issued on the 1st of each month for the month before.</p></div>
                    @endforelse
                </div>

                @if ($statements->hasPages())<div class="border-t border-line bg-canvas px-4 py-4">{{ $statements->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
