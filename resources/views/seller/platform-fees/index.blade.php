<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Platform fees</h1>            </div>
        </div>
    </x-slot>

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<div class="sc-notice" data-tone="success" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="sc-notice" data-tone="danger" role="alert">{{ $errors->first() }}</div>@endif

            @if ($tenant->platform_fee_accepted_at === null)
                <section class="sc-card sc-card-pad border-blue-200" aria-labelledby="fee-terms-heading">
                    <h2 id="fee-terms-heading" class="sc-card-title">Accept the Marketplace Fee Policy</h2>
                    <p class="mt-1 sc-card-note">Your store was approved before these terms existed. Nothing is charged until they are accepted, and only orders delivered afterwards count.</p>
                    @include('partials.platform-fee-terms', ['class' => 'mt-4'])
                    @if ($isOwner)
                        <form method="POST" action="{{ route('seller.platform-fees.accept') }}" class="mt-5 flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">@csrf
                            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="accept" value="1" required class="mt-0.5 rounded border-line text-brand focus:ring-brand"> <span>I accept the Marketplace Fee Policy (version {{ config('marketplace.platform_fee.policy_version') }}).</span></label>
                            <button type="submit" class="seller-primary-button min-h-11 shrink-0">Accept terms</button>
                        </form>
                    @else
                        <p class="mt-4 text-sm font-medium text-ink">Only the store owner can accept these terms.</p>
                    @endif
                </section>
            @endif

            <section class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]" aria-label="Platform fee summary">
                <dl class="sc-metrics !grid-cols-1">
                    <div class="sc-metric"><dt>Not yet billed</dt><dd><strong class="sc-metric-value">₱{{ number_format((float) $unbilledTotal, 2) }}</strong><span class="sc-metric-context">Fees on delivered orders that go on your next statement.</span></dd></div>
                </dl>
                <div class="sc-card sc-card-pad">
                    <p class="text-[13px] font-medium text-muted">How to pay</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink">{{ $paymentInstructions ?: 'JMMart will email you the bank and GCash details with your first statement.' }}</p>
                    <p class="mt-2 sc-meta">After paying, upload the reference number and receipt on the statement below.</p>
                </div>
            </section>

            <section class="sc-card" aria-labelledby="statements-heading">
                <div class="sc-card-head">
                    <div><h2 id="statements-heading" class="sc-card-title">Monthly statements</h2><p class="sc-card-note">Each statement is due {{ config('marketplace.platform_fee.due_days') }} days after its month ends.</p></div>
                </div>

                <div class="sc-list">
                    @forelse ($statements as $statement)
                        @php($badge = match (true) {
                            (float) $statement->amount_due === 0.0 => ['Nothing due', 'neutral'],
                            $statement->status === 'paid' => ['Paid', 'success'],
                            $statement->status === 'submitted' => ['Awaiting confirmation', 'info'],
                            $statement->isOverdue() => ['Overdue', 'danger'],
                            default => ['Due '.$statement->due_at->format('M j, Y'), 'warning'],
                        })
                        <article class="sc-list-item !py-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-ink">{{ $statement->period_start->format('F Y') }}</h3><span class="sc-badge" data-tone="{{ $badge[1] }}">{{ $badge[0] }}</span></div>
                                    <p class="mt-1 sc-meta">₱{{ number_format((float) $statement->gross_total, 2) }} delivered · ₱{{ number_format((float) $statement->commission_total, 2) }} fees @if ((float) $statement->refund_credit > 0)· −₱{{ number_format((float) $statement->refund_credit, 2) }} refund credit @endif @if ((float) $statement->credit_carried_in > 0)· −₱{{ number_format((float) $statement->credit_carried_in, 2) }} earlier credit @endif</p>
                                    @if ((float) $statement->credit_carried_out > 0)<p class="mt-1 sc-meta">₱{{ number_format((float) $statement->credit_carried_out, 2) }} credit carries to your next statement.</p>@endif
                                </div>
                                <p class="shrink-0 text-xl font-semibold tabular-nums text-ink sm:text-right">₱{{ number_format((float) $statement->amount_due, 2) }}</p>
                            </div>

                            @if ($statement->review_note)<p class="sc-notice mt-3" data-tone="danger" role="status">JMMart could not confirm your last payment: {{ $statement->review_note }}</p>@endif
                            @if ($statement->payment_reference)<p class="mt-3 sc-meta">Reference {{ $statement->payment_reference }} · <a href="{{ route('seller.platform-fees.proof', $statement) }}" class="font-semibold text-brand underline">View receipt</a></p>@endif

                            @if ($statement->status === 'unpaid' && (float) $statement->amount_due > 0 && $isOwner)
                                <form method="POST" action="{{ route('seller.platform-fees.pay', $statement) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded-lg border border-line bg-[#f8fafc] p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf
                                    <div class="sc-field"><label for="reference-{{ $statement->id }}">Transfer reference number</label><input id="reference-{{ $statement->id }}" name="reference" type="text" required maxlength="120" class="sc-input"></div>
                                    <div class="sc-field"><label for="proof-{{ $statement->id }}">Receipt (image or PDF, max 5 MB)</label><input id="proof-{{ $statement->id }}" name="proof" type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-ink file:ring-1 file:ring-line"></div>
                                    <button type="submit" class="seller-primary-button min-h-11">Submit payment</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="sc-empty"><h3>No statements yet</h3><p>Your first statement arrives at the start of the month after an order is delivered.</p></div>
                    @endforelse
                </div>

                @if ($statements->hasPages())<div class="sc-card-foot">{{ $statements->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
