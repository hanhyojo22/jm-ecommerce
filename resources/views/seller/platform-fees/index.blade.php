<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-[28px] font-bold tracking-[-0.02em] text-ink leading-[1.3]">Platform fees</h1>
            <p class="mt-2 text-sm text-muted">JMMart's share of your delivered orders, billed once a month.</p>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page">
            @if (session('status'))<div class="mb-5 rounded-2xl border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="mb-5 rounded-2xl border border-danger/20 bg-danger/10 px-4 py-3 text-sm font-medium text-danger" role="alert">{{ $errors->first() }}</div>@endif

            @if ($tenant->platform_fee_accepted_at === null)
                <section class="mb-6 section-card" aria-labelledby="fee-terms-heading">
                    <h2 id="fee-terms-heading" class="text-lg font-semibold text-ink">Accept the Marketplace Fee Policy</h2>
                    <p class="mt-1 text-sm text-muted">Your store was approved before these terms existed. Nothing is charged until they are accepted, and only orders delivered afterwards count.</p>
                    @include('partials.platform-fee-terms', ['class' => 'mt-4'])
                    @if ($isOwner)
                        <form method="POST" action="{{ route('seller.platform-fees.accept') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">@csrf
                            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="accept" value="1" required class="mt-1 rounded border-line text-brand focus:ring-brand"> <span>I accept the Marketplace Fee Policy (version {{ config('marketplace.platform_fee.policy_version') }}).</span></label>
                            <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Accept terms</button>
                        </form>
                    @else
                        <p class="mt-4 text-sm font-medium text-ink">Only the store owner can accept these terms.</p>
                    @endif
                </section>
            @endif

            <section class="grid gap-4 sm:grid-cols-2" aria-label="Platform fee summary">
                <article class="section-card">
                    <p class="text-sm font-medium text-muted">Not yet billed</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format((float) $unbilledTotal, 2) }}</p>
                    <p class="mt-2 text-sm text-muted">Fees on delivered orders that go on your next statement.</p>
                </article>
                <article class="section-card">
                    <p class="text-sm font-medium text-muted">How to pay</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-ink">{{ $paymentInstructions ?: 'JMMart will email you the bank and GCash details with your first statement.' }}</p>
                    <p class="mt-2 text-sm text-muted">After paying, upload the reference number and receipt on the statement below.</p>
                </article>
            </section>

            <section class="mt-6 content-card" aria-labelledby="statements-heading">
                <div class="border-b border-line px-5 py-4 sm:px-6">
                    <h2 id="statements-heading" class="text-lg font-semibold text-ink">Monthly statements</h2>
                    <p class="mt-1 text-sm text-muted">Each statement is due {{ config('marketplace.platform_fee.due_days') }} days after its month ends.</p>
                </div>

                <div class="divide-y divide-line">
                    @forelse ($statements as $statement)
                        <article class="p-5 sm:px-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-ink">{{ $statement->period_start->format('F Y') }}</h3>
                                    <p class="mt-1 text-sm text-muted">₱{{ number_format((float) $statement->gross_total, 2) }} delivered · ₱{{ number_format((float) $statement->commission_total, 2) }} fees @if ((float) $statement->refund_credit > 0)· −₱{{ number_format((float) $statement->refund_credit, 2) }} refund credit @endif @if ((float) $statement->credit_carried_in > 0)· −₱{{ number_format((float) $statement->credit_carried_in, 2) }} earlier credit @endif</p>
                                    @if ((float) $statement->credit_carried_out > 0)<p class="mt-1 text-sm text-muted">₱{{ number_format((float) $statement->credit_carried_out, 2) }} credit carries to your next statement.</p>@endif
                                </div>
                                <div class="shrink-0 sm:text-right">
                                    <p class="text-xl font-semibold tabular-nums text-ink">₱{{ number_format((float) $statement->amount_due, 2) }}</p>
                                    @php($badge = match (true) {
                                        (float) $statement->amount_due === 0.0 => ['Nothing due', 'bg-slate-100 text-muted ring-line'],
                                        $statement->status === 'paid' => ['Paid', 'bg-success/10 text-success ring-success/20'],
                                        $statement->status === 'submitted' => ['Awaiting confirmation', 'bg-blue-50 text-brand-hover ring-blue-200'],
                                        $statement->isOverdue() => ['Overdue', 'bg-danger/10 text-danger ring-danger/20'],
                                        default => ['Due '.$statement->due_at->format('M j, Y'), 'bg-amber-50 text-amber-800 ring-amber-200'],
                                    })
                                    <span class="status-badge mt-1 {{ $badge[1] }}">{{ $badge[0] }}</span>
                                </div>
                            </div>

                            @if ($statement->review_note)<p class="mt-3 rounded-lg border border-danger/20 bg-danger/10 px-3 py-2 text-sm text-danger" role="status">JMMart could not confirm your last payment: {{ $statement->review_note }}</p>@endif
                            @if ($statement->payment_reference)<p class="mt-3 text-sm text-muted">Reference {{ $statement->payment_reference }} · <a href="{{ route('seller.platform-fees.proof', $statement) }}" class="font-semibold text-brand underline">View receipt</a></p>@endif

                            @if ($statement->status === 'unpaid' && (float) $statement->amount_due > 0 && $isOwner)
                                <form method="POST" action="{{ route('seller.platform-fees.pay', $statement) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded-lg bg-canvas p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf
                                    <div><label for="reference-{{ $statement->id }}" class="field-label">Transfer reference number</label><input id="reference-{{ $statement->id }}" name="reference" type="text" required maxlength="120" class="mt-1 block w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand"></div>
                                    <div><label for="proof-{{ $statement->id }}" class="field-label">Receipt (image or PDF, max 5 MB)</label><input id="proof-{{ $statement->id }}" name="proof" type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1 block w-full text-sm"></div>
                                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Submit payment</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state"><p class="font-semibold text-ink">No statements yet</p><p class="mt-2 text-sm text-muted">Your first statement arrives at the start of the month after an order is delivered.</p></div>
                    @endforelse
                </div>

                @if ($statements->hasPages())<div class="border-t border-line bg-canvas px-4 py-4">{{ $statements->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
