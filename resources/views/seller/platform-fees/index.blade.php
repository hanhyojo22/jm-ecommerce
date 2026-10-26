<x-app-layout>
    <x-slot name="header">
        <div class="sc-page-head">
            <div>
                <h1 class="sc-title">Platform fees</h1>
                <p class="sc-subtitle">JMMart bills its {{ rtrim(rtrim(bcmul((string) config('marketplace.platform_fee.rate'), '100', 2), '0'), '.') }}% fee on delivered orders once a month.</p>
            </div>
        </div>
    </x-slot>

    @php($money = fn ($amount): string => '₱'.number_format((float) $amount, 2))
    @php($nextIssue = now()->toImmutable()->startOfMonth()->addMonthNoOverflow())

    <div class="page-shell">
        <div class="page-container seller-workspace-page sc-stack">
            @if (session('status'))<div class="sc-notice" data-tone="success" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="sc-notice" data-tone="danger" role="alert">{{ $errors->first() }}</div>@endif

            @if (! $tenant->hasAcceptedCurrentFeeTerms())
                <section class="sc-card sc-card-pad border-blue-200" aria-labelledby="fee-terms-heading">
                    @if ($tenant->platform_fee_accepted_at === null)
                        <h2 id="fee-terms-heading" class="sc-card-title">Accept the Marketplace Fee Policy</h2>
                        <p class="mt-1 sc-card-note">Your store was approved before these terms existed. Nothing is charged until they are accepted, and only orders delivered afterwards count.</p>
                    @else
                        <h2 id="fee-terms-heading" class="sc-card-title">The Marketplace Fee Policy has changed</h2>
                        <p class="mt-1 sc-card-note">You agreed to version {{ $tenant->platform_fee_policy_version ?? 'an earlier version' }}. Until you accept version {{ config('marketplace.platform_fee.policy_version') }}, orders are charged {{ rtrim(rtrim(bcmul($tenant->platformFeeRateAt(now()), '100', 2), '0'), '.') }}%, never more than you agreed to.</p>
                    @endif
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

            @if ($openStatements->isNotEmpty())
                @php($oldest = $openStatements->first())
                @php($anyOverdue = $openStatements->contains(fn ($statement): bool => $statement->isOverdue()))
                <section class="sc-notice flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-tone="{{ $anyOverdue ? 'danger' : ($openStatements->every(fn ($statement): bool => $statement->status === 'submitted') ? 'info' : 'warning') }}" aria-labelledby="balance-heading">
                    <div>
                        <h2 id="balance-heading" class="text-base font-semibold">
                            {{ $money($openStatements->sum('amount_due')) }} open across {{ trans_choice('{1} :count statement|[2,*] :count statements', $openStatements->count()) }}
                        </h2>
                        <p class="mt-1 text-sm">
                            @if ($anyOverdue)
                                {{ $oldest->period_start->format('F Y') }} was due {{ $oldest->due_at->format('M j, Y') }}. JMMart may suspend stores with unpaid fees.
                            @elseif ($openStatements->every(fn ($statement): bool => $statement->status === 'submitted'))
                                Your payment is with JMMart for confirmation. Nothing else to do.
                            @else
                                Pay {{ $oldest->period_start->format('F Y') }} by {{ $oldest->due_at->format('M j, Y') }}.
                            @endif
                        </p>
                    </div>
                    @if ($oldest->status === 'unpaid' && $isOwner)<a href="#statement-{{ $oldest->id }}" class="seller-primary-button min-h-11 shrink-0">Pay {{ $oldest->period_start->format('F') }}</a>@endif
                </section>
            @endif

            <section class="grid gap-5 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]" aria-label="Platform fee summary">
                <div class="sc-card sc-card-pad" aria-labelledby="next-statement-heading">
                    <h2 id="next-statement-heading" class="sc-card-title">Next statement so far</h2>
                    <p class="mt-1 sc-card-note">Issued {{ $nextIssue->format('M j') }}, due {{ $nextIssue->addDays((int) config('marketplace.platform_fee.due_days'))->subDay()->format('M j, Y') }}. It grows as orders are delivered.</p>
                    <dl class="sc-sum mt-4">
                        <div><dt>Fees on delivered orders</dt><dd>{{ $money($preview['commission']) }}</dd></div>
                        @if ((float) $preview['refund_credit'] > 0)<div><dt>Refund credit</dt><dd class="text-success">−{{ $money($preview['refund_credit']) }}</dd></div>@endif
                        @if ((float) $preview['credit_carried_in'] > 0)<div><dt>Credit from last statement</dt><dd class="text-success">−{{ $money($preview['credit_carried_in']) }}</dd></div>@endif
                        <div class="is-total"><dt>Estimated amount due</dt><dd>{{ $money($preview['amount_due']) }}</dd></div>
                    </dl>
                </div>
                <div class="sc-card sc-card-pad">
                    <h2 class="sc-card-title">How to pay</h2>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink">{{ $paymentInstructions ?: 'JMMart will email you the bank and GCash details with your first statement.' }}</p>
                    <p class="mt-3 sc-meta">After paying, add the reference number and receipt to the statement below. JMMart confirms it within a few business days.</p>
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
                            $statement->status === 'paid' => ['Paid '.$statement->paid_at?->format('M j'), 'success'],
                            $statement->status === 'submitted' => ['Awaiting confirmation', 'info'],
                            $statement->isOverdue() => ['Overdue', 'danger'],
                            default => ['Due '.$statement->due_at->format('M j, Y'), 'warning'],
                        })
                        <article id="statement-{{ $statement->id }}" class="sc-list-item scroll-mt-24 !py-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-ink">{{ $statement->period_start->format('F Y') }}</h3>
                                    <span class="sc-badge mt-1" data-tone="{{ $badge[1] }}">{{ $badge[0] }}</span>
                                </div>
                                <p class="shrink-0 text-right text-xl font-semibold tabular-nums text-ink">{{ $money($statement->amount_due) }}</p>
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                                <dl class="sc-sum">
                                    <div><dt>Fees on {{ $money($statement->gross_total) }} delivered</dt><dd>{{ $money($statement->commission_total) }}</dd></div>
                                    @if ((float) $statement->refund_credit > 0)<div><dt>Refund credit</dt><dd class="text-success">−{{ $money($statement->refund_credit) }}</dd></div>@endif
                                    @if ((float) $statement->credit_carried_in > 0)<div><dt>Credit from last statement</dt><dd class="text-success">−{{ $money($statement->credit_carried_in) }}</dd></div>@endif
                                    <div class="is-total"><dt>Amount due</dt><dd>{{ $money($statement->amount_due) }}</dd></div>
                                    @if ((float) $statement->credit_carried_out > 0)<div><dt>Credit carried to next statement</dt><dd>{{ $money($statement->credit_carried_out) }}</dd></div>@endif
                                </dl>

                                @if ($statement->earnings->isNotEmpty())
                                    <details class="group rounded-lg border border-line">
                                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-brand">
                                            {{ trans_choice('{1} :count order on this statement|[2,*] :count orders on this statement', $statement->earnings->count()) }}
                                            <svg class="size-4 shrink-0 text-muted transition-transform group-open:rotate-180 motion-reduce:transition-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                                        </summary>
                                        <div class="sc-table-wrap max-h-72 overflow-y-auto border-t border-line">
                                            <table class="sc-table">
                                                <thead><tr><th scope="col">Order</th><th scope="col">Delivered</th><th scope="col" class="is-num">Total</th><th scope="col" class="is-num">Fee</th></tr></thead>
                                                <tbody>
                                                    @foreach ($statement->earnings->sortBy('available_at') as $earning)
                                                        <tr>
                                                            <th scope="row"><a href="{{ route('seller.orders.show', $earning->seller_order_id) }}" class="sc-link">#{{ \App\Models\Order::shortNumber((string) $earning->sellerOrder?->order?->number) }}</a></th>
                                                            <td class="whitespace-nowrap">{{ $earning->available_at->format('M j') }}</td>
                                                            <td class="is-num">{{ $money($earning->gross_amount) }}</td>
                                                            <td class="is-num">{{ $money($earning->commission_amount) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                @endif
                            </div>

                            @if ($statement->review_note && $statement->status === 'unpaid')<p class="sc-notice mt-4" data-tone="danger" role="status">JMMart could not confirm your last payment: {{ $statement->review_note }}</p>@endif
                            @if ($statement->payment_reference)<p class="mt-3 sc-meta">Reference {{ $statement->payment_reference }}@if ($statement->payment_submitted_at), submitted {{ $statement->payment_submitted_at->format('M j, Y') }}@endif. @if ($statement->payment_proof_path)<a href="{{ route('seller.platform-fees.proof', $statement) }}" class="font-semibold text-brand underline">View receipt</a>@endif</p>@endif

                            @if ($statement->status === 'unpaid' && (float) $statement->amount_due > 0 && $isOwner)
                                <form method="POST" action="{{ route('seller.platform-fees.pay', $statement) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded-lg border border-line bg-[#f8fafc] p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf
                                    <div class="sc-field"><label for="reference-{{ $statement->id }}">Transfer reference number</label><input id="reference-{{ $statement->id }}" name="reference" type="text" required maxlength="120" autocomplete="off" class="sc-input"></div>
                                    <div class="sc-field"><label for="proof-{{ $statement->id }}">Receipt (image or PDF, max 5 MB)</label><input id="proof-{{ $statement->id }}" name="proof" type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-ink file:ring-1 file:ring-line"></div>
                                    <button type="submit" class="seller-primary-button min-h-11">Submit {{ $money($statement->amount_due) }} payment</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="sc-empty"><h3>No statements yet</h3><p>Your first statement arrives on {{ $nextIssue->format('M j') }} if an order is delivered this month.</p></div>
                    @endforelse
                </div>

                @if ($statements->hasPages())<div class="sc-card-foot">{{ $statements->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
