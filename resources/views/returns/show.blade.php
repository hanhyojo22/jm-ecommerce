<x-app-layout>
    @php
        $actionRoute = route($role === 'buyer' ? 'returns.update' : ($role === 'seller' ? 'seller.returns.update' : 'admin.returns.update'), $case);
        $deadline = $case->deadline();
        $overdue = $case->isOverdue();
        $isTerminal = in_array($case->status, ['completed', 'denied', 'closed'], true);
        $amountAtStake = $case->items->reduce(fn (string $sum, $item): string => bcadd($sum, bcmul((string) $item->unit_price, (string) $item->quantity, 2), 2), '0.00');
        $unitsInCase = $case->items->sum('quantity');
        $reasonLabel = ucfirst(str_replace('_', ' ', $case->reason));
        $backUrl = $role === 'buyer'
            ? route('orders.show', $case->sellerOrder->order)
            : route($role === 'seller' ? 'seller.returns.index' : 'admin.returns.index');
        $backLabel = $role === 'buyer' ? 'Order '.$case->sellerOrder->order->number : ($role === 'seller' ? 'Returns' : 'Return cases');

        $statusCopy = match ($case->status) {
            'awaiting_seller' => 'The seller is reviewing the request and aims to respond within 48 hours of it being sent.',
            'offered' => 'The seller offered a remedy. Accept it to start the return, or ask JMMart to review it.',
            'rejected' => 'The seller rejected the request. The buyer can ask JMMart to review the decision.',
            'awaiting_return' => 'The seller approved a remedy. The buyer sends the goods back using the agreed method, then records the handoff here.',
            'awaiting_receipt' => 'The goods are on their way back. The seller confirms the parcel on arrival.',
            'awaiting_fulfillment' => 'The seller is completing the approved remedy: refund or replacement.',
            'awaiting_confirmation' => 'An outcome was recorded. The buyer confirms it, or disputes it for JMMart review.',
            'appealed' => 'JMMart support is reviewing this case. They can decide it, or hand it back to the seller.',
            'completed' => 'The case is complete and the outcome was confirmed.',
            'denied' => 'JMMart denied this claim. The reasons are recorded in the decision below.',
            'closed' => 'JMMart closed this case because its deadline passed without a response.',
            default => '',
        };

        $overdueCopy = match (true) {
            $role === 'buyer' && in_array($case->status, ['offered', 'rejected', 'awaiting_confirmation', 'awaiting_return'], true) => 'Your response window has passed. JMMart can close the case without a remedy — contact support if you still want to respond.',
            $role === 'buyer' => 'The seller missed this deadline. JMMart can step in — use the action panel below to ask for their review.',
            $role === 'seller' => 'This case is overdue. JMMart can step in at any time — complete your step now to keep the resolution on your side.',
            default => 'The current party missed this deadline. Take the case over or close it using the options in the action panel.',
        };
    @endphp

    <div class="page-shell"><div class="page-container max-w-5xl py-8">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-muted">
            <a href="{{ $backUrl }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:text-brand-hover">{{ $backLabel }}</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page" class="font-semibold text-ink">Return case</span>
        </nav>

        @php
            $storeInitials = Str::upper(Str::substr($case->sellerOrder->store->name, 0, 2));
        @endphp

        <section class="returns-hero mt-4" aria-labelledby="case-title">
            <div class="returns-hero-content">
                <div class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <span class="returns-monogram" aria-hidden="true">{{ $storeInitials }}</span>
                        <div class="min-w-0">
                            <p class="returns-eyebrow">Return case · {{ $case->sellerOrder->store->name }}</p>
                            <h1 id="case-title" class="mt-1.5 break-words text-3xl font-bold tracking-[-0.03em] text-ink">Order {{ $case->sellerOrder->order->number }}</h1>
                            <p class="mt-2 text-sm leading-6 text-muted">
                                @if ($role !== 'buyer')
                                    Buyer {{ $case->buyer->name }} ·
                                @endif
                                opened {{ $case->created_at->format('M j, Y') }}
                                @if ($case->opened_by !== $case->buyer_id)
                                    by JMMart support
                                @endif
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <x-returns.status-badge :status="$case->status" />
                                @if ($role !== 'buyer')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/70 px-2.5 py-1 text-xs font-semibold text-muted ring-1 ring-inset ring-line">
                                        <x-returns.icon name="store" class="size-3.5" />
                                        {{ $case->sellerOrder->store->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-start gap-2 lg:items-end">
                        <x-returns.deadline :case="$case" />
                        <p class="text-xs text-muted">{{ $unitsInCase }} {{ Str::plural('unit', $unitsInCase) }} in this case</p>
                    </div>
                </div>

                <dl class="returns-hero-stats">
                    <div class="returns-hero-stat">
                        <dt>Amount at stake</dt>
                        <dd class="tabular-nums">₱{{ number_format((float) $amountAtStake, 2) }}</dd>
                    </div>
                    <div class="returns-hero-stat">
                        <dt>Items in case</dt>
                        <dd>{{ $unitsInCase }} {{ Str::plural('unit', $unitsInCase) }}</dd>
                    </div>
                    <div class="returns-hero-stat">
                        <dt>Buyer asked for</dt>
                        <dd>{{ ucfirst($case->requested_remedy) }}</dd>
                    </div>
                    <div class="returns-hero-stat">
                        <dt>Current deadline</dt>
                        <dd>
                            @if ($deadline)
                                <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('M j, Y · g:i A') }}</time>
                            @else
                                No fixed deadline
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        @if (session('status'))
            <p class="mt-5 flex items-start gap-2.5 rounded-[10px] border border-green-200 bg-green-50 px-3.5 py-3 text-sm font-semibold text-success" role="status">
                <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                {{ session('status') }}
            </p>
        @endif
        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-rose-800" role="alert">
                <p class="font-semibold">The last action could not be completed.</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($overdue)
            <div class="mt-5 flex items-start gap-3 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3.5" role="alert">
                <svg class="mt-0.5 size-5 shrink-0 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                <div>
                    <p class="text-sm font-bold text-rose-800">This case is overdue</p>
                    <p class="mt-1 text-sm leading-6 text-rose-800/90">{{ $overdueCopy }}</p>
                </div>
            </div>
        @endif

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_330px]">
            <div class="min-w-0 space-y-5">
                <section class="section-card" aria-labelledby="case-progress-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="case-progress-heading" class="text-base font-semibold text-ink">Case progress</h2>
                        <x-returns.deadline :case="$case" />
                    </div>
                    <p class="mt-2 text-sm leading-6 text-muted">{{ $statusCopy }}</p>
                    <x-returns.progress :case="$case" :role="$role" class="mt-5" />
                </section>

                {{-- One action panel per role and case leg: the fields and button names match the
                     same routes and validation the case workflow always used. --}}
                <section class="section-card returns-action-panel" id="next-step" aria-labelledby="next-step-heading">
                    @if ($role === 'seller' && $case->status === 'awaiting_seller')
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Your turn: respond to the request</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">Approve with a remedy or reject with reasons. The buyer sees your response and can appeal it to JMMart, so explain the decision clearly.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-5" x-data="{ waived: {{ old('return_waived', $case->return_waived) ? 'true' : 'false' }} }">@csrf @method('PATCH')
                            <fieldset>
                                <legend class="field-label">Your response</legend>
                                <label for="seller-note" class="sr-only">Response with reasons</label>
                                <textarea id="seller-note" name="note" required minlength="10" maxlength="2000" rows="4" class="field-control" placeholder="Explain your decision on the request.">{{ old('note') }}</textarea>
                                <p class="mt-1.5 text-xs text-muted">Shown to the buyer and JMMart support. At least 10 characters.</p>
                                <x-input-error :messages="$errors->get('note')" class="mt-2" />
                            </fieldset>
                            <fieldset>
                                <legend class="field-label">Remedy you are offering</legend>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="cursor-pointer">
                                        <span class="flex h-full items-start gap-3 rounded-[10px] border border-line bg-white p-3.5 transition hover:border-blue-300 has-[:checked]:border-brand has-[:checked]:bg-blue-50/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                                            <input type="radio" name="remedy" value="refund" class="peer sr-only" @checked(old('remedy', $case->requested_remedy) === 'refund')>
                                            <span class="mt-0.5 size-4 shrink-0 rounded-full border border-slate-400 bg-white transition peer-checked:border-brand peer-checked:bg-brand" aria-hidden="true"></span>
                                            <span class="min-w-0"><span class="block text-sm font-semibold text-ink">Refund</span><span class="mt-0.5 block text-xs leading-5 text-muted">Return the payment for the affected units.</span></span>
                                        </span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <span class="flex h-full items-start gap-3 rounded-[10px] border border-line bg-white p-3.5 transition hover:border-blue-300 has-[:checked]:border-brand has-[:checked]:bg-blue-50/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                                            <input type="radio" name="remedy" value="replacement" class="peer sr-only" @checked(old('remedy', $case->requested_remedy) === 'replacement')>
                                            <span class="mt-0.5 size-4 shrink-0 rounded-full border border-slate-400 bg-white transition peer-checked:border-brand peer-checked:bg-brand" aria-hidden="true"></span>
                                            <span class="min-w-0"><span class="block text-sm font-semibold text-ink">Replacement</span><span class="mt-0.5 block text-xs leading-5 text-muted">Ship the same item again, if stock allows.</span></span>
                                        </span>
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('remedy')" class="mt-2" />
                            </fieldset>
                            <fieldset class="grid gap-4">
                                <legend class="field-label">Return of the goods</legend>
                                <label class="flex cursor-pointer items-start gap-3 rounded-[10px] border border-line bg-canvas px-3.5 py-3">
                                    <input type="checkbox" name="return_waived" value="1" x-model="waived" class="mt-0.5 rounded border-line text-brand focus:ring-brand">
                                    <span>
                                        <span class="block text-sm font-semibold text-ink">Waive the physical return</span>
                                        <span class="mt-0.5 block text-xs leading-5 text-muted">Skip sending the goods back and complete the remedy directly.</span>
                                    </span>
                                </label>
                                {{-- The workflow only demands these on an offer, so a rejection must
                                     not be blocked by them; the server reports a missing pair. --}}
                                <div class="grid gap-4" x-show="! waived">
                                    <div>
                                        <label for="seller-return-instructions" class="field-label">Return instructions</label>
                                        <textarea id="seller-return-instructions" name="return_instructions" rows="3" maxlength="2000" class="field-control" placeholder="How and where to send the goods back.">{{ old('return_instructions') }}</textarea>
                                        <p class="mt-1.5 text-xs text-muted">Needed to send an offer, unless the return is waived.</p>
                                    </div>
                                    <div>
                                        <label for="seller-return-method" class="field-label">No-cost return method</label>
                                        <input id="seller-return-method" name="return_method" value="{{ old('return_method') }}" maxlength="500" class="field-control" placeholder="Seller-paid pickup or prepaid drop-off">
                                        <p class="mt-1.5 text-xs text-muted">The seller pays for the return.</p>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('return_instructions')" />
                            </fieldset>
                            <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
                                <button type="submit" name="action" value="offer" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Send offer</button>
                                <button type="submit" name="action" value="reject" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-red-200 bg-white px-5 text-sm font-semibold text-danger transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2" x-data @click="if (! window.confirm('Reject this request? The buyer can appeal to JMMart and your reasons are recorded.')) $event.preventDefault()">Reject with reason</button>
                            </div>
                        </form>
                    @elseif ($role === 'buyer' && in_array($case->status, ['offered', 'rejected'], true))
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Your turn: respond to the seller</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">Respond by <strong>{{ $case->buyer_respond_by?->format('M j, g:i A') }}</strong>. After that JMMart can close the case without a remedy.</p>
                        @if ($case->status === 'offered')
                            <div class="mt-4 rounded-[10px] border border-blue-200 bg-blue-50/60 px-4 py-3.5">
                                <p class="text-sm font-semibold text-ink">Offer: {{ ucfirst($case->remedy) }}</p>
                                @if ($case->return_waived)
                                    <p class="mt-1 text-sm text-muted">No return needed — the seller completes the remedy directly.</p>
                                @elseif ($case->return_instructions)
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-muted">{{ $case->return_instructions }}<br>No-cost method: {{ $case->return_method }}</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ $actionRoute }}" class="mt-4">@csrf @method('PATCH')
                                <button type="submit" name="action" value="accept" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Accept {{ $case->remedy }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-3 border-t border-line pt-5">@csrf @method('PATCH')
                            <div>
                                <label for="buyer-appeal-note" class="field-label">{{ $case->status === 'offered' ? 'Not happy with the offer?' : 'Why should support review this?' }}</label>
                                <textarea id="buyer-appeal-note" name="note" required minlength="10" maxlength="2000" rows="3" class="field-control" placeholder="Explain what should happen instead.">{{ old('note') }}</textarea>
                                <p class="mt-1.5 text-xs text-muted">JMMart reviews the case and can decide the remedy themselves.</p>
                                <x-input-error :messages="$errors->get('note')" class="mt-2" />
                            </div>
                            <button type="submit" name="action" value="appeal" class="inline-flex min-h-11 w-fit items-center justify-center rounded-[10px] border border-brand bg-white px-5 text-sm font-semibold text-brand-hover transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Appeal to JMMart</button>
                        </form>
                    @elseif ($role === 'buyer' && $case->status === 'awaiting_return')
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Your turn: send the goods back</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">Use the agreed method, then record the handoff with proof. If you do not send the goods back, JMMart can close the case without a remedy.</p>
                        <form method="POST" action="{{ $actionRoute }}" enctype="multipart/form-data" class="mt-5 grid gap-4">@csrf @method('PATCH')
                            <div>
                                <label for="buyer-handoff-proof" class="field-label">Handoff proof</label>
                                <input id="buyer-handoff-proof" type="file" name="proof" required accept="image/jpeg,image/png,image/webp,application/pdf" class="field-control">
                                <p class="mt-1.5 text-xs text-muted">A photo or PDF up to 5 MB — for example the courier receipt or the packed parcel.</p>
                                <x-input-error :messages="$errors->get('proof')" class="mt-2" />
                            </div>
                            <button type="submit" name="action" value="handoff" class="inline-flex min-h-11 w-fit items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Record handoff</button>
                        </form>
                    @elseif ($role === 'seller' && $case->status === 'awaiting_receipt')
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Your turn: confirm what arrived</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">Inspect the parcel against the claim, then record the returned goods. The remedy clock starts when you do.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5">@csrf @method('PATCH')
                            <button type="submit" name="action" value="receive" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Record returned goods received</button>
                        </form>
                    @elseif ($role === 'seller' && $case->status === 'awaiting_fulfillment' && $case->remedy === 'refund')
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Your turn: record the refund</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">Support coordinates the refund destination privately. Record proof only after payment. The original order total stays unchanged.</p>
                        <p class="mt-3 rounded-[10px] border border-line bg-canvas px-3.5 py-2.5 text-sm text-ink">Refund amount: <strong class="tabular-nums">₱{{ number_format((float) $amountAtStake, 2) }}</strong></p>
                        <form method="POST" action="{{ $actionRoute }}" enctype="multipart/form-data" class="mt-4 grid gap-4">@csrf @method('PATCH')
                            <div>
                                <label for="seller-paid-at" class="field-label">Payment date</label>
                                <input id="seller-paid-at" type="date" name="paid_at" value="{{ old('paid_at') }}" required max="{{ now()->toDateString() }}" class="field-control">
                                <x-input-error :messages="$errors->get('paid_at')" class="mt-2" />
                            </div>
                            <div>
                                <label for="seller-refund-proof" class="field-label">Payment proof</label>
                                <input id="seller-refund-proof" type="file" name="proof" required accept="image/jpeg,image/png,image/webp,application/pdf" class="field-control">
                                <p class="mt-1.5 text-xs text-muted">A photo or PDF up to 5 MB, for example the transfer receipt.</p>
                                <x-input-error :messages="$errors->get('proof')" class="mt-2" />
                            </div>
                            <button type="submit" name="action" value="refund" class="inline-flex min-h-11 w-fit items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Record seller-paid refund</button>
                        </form>
                    @elseif ($role === 'seller' && $case->status === 'awaiting_fulfillment' && $case->remedy === 'replacement')
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Your turn: ship the replacement</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">Ship the same items again and record the reference. Stock is reserved when you record the shipment.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-4">@csrf @method('PATCH')
                            <div>
                                <label for="seller-reference" class="field-label">Shipment reference</label>
                                <input id="seller-reference" name="reference" value="{{ old('reference') }}" required maxlength="255" class="field-control" placeholder="Courier tracking number or waybill">
                                <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                            </div>
                            <button type="submit" name="action" value="ship" class="inline-flex min-h-11 w-fit items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Record replacement shipment</button>
                        </form>
                    @elseif ($role === 'buyer' && $case->status === 'awaiting_confirmation')
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Your turn: confirm the outcome</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">Confirm that you received the {{ $case->remedy }}, or dispute it and JMMart will review the recorded outcome.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5">@csrf @method('PATCH')
                            <button type="submit" name="action" value="confirm" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Confirm receipt</button>
                        </form>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-3 border-t border-line pt-5">@csrf @method('PATCH')
                            <div>
                                <label for="buyer-dispute-note" class="field-label">Not received or not right?</label>
                                <textarea id="buyer-dispute-note" name="note" required minlength="10" maxlength="2000" rows="3" class="field-control" placeholder="Explain what is still wrong.">{{ old('note') }}</textarea>
                                <x-input-error :messages="$errors->get('note')" class="mt-2" />
                            </div>
                            <button type="submit" name="action" value="appeal" class="inline-flex min-h-11 w-fit items-center justify-center rounded-[10px] border border-brand bg-white px-5 text-sm font-semibold text-brand-hover transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Dispute outcome</button>
                        </form>
                    @elseif ($role === 'admin' && $case->status === 'appealed' && $case->outcome)
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Review the recorded outcome</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">An outcome was recorded before this dispute. Verify it to close the case, or set it aside to put the seller back on the hook for the remedy.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-4">@csrf @method('PATCH')
                            <div>
                                <label for="admin-verify-note" class="field-label">Verification evidence and decision</label>
                                <textarea id="admin-verify-note" name="note" required minlength="10" maxlength="2000" rows="4" class="field-control" placeholder="Summarise what the proof shows and why it stands or fails.">{{ old('note') }}</textarea>
                                <x-input-error :messages="$errors->get('note')" class="mt-2" />
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="submit" name="action" value="verify" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-green-300 bg-white px-5 text-sm font-semibold text-success transition hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-success focus:ring-offset-2">Verify completion</button>
                                <button type="submit" name="action" value="reopen" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-red-200 bg-white px-5 text-sm font-semibold text-danger transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2" x-data @click="if (! window.confirm('Set the recorded outcome aside? The seller must fulfil the remedy again.')) $event.preventDefault()">Set outcome aside and reopen</button>
                            </div>
                        </form>
                    @elseif ($role === 'admin' && $case->status === 'appealed')
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Decide the appeal</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">Weigh the request, the seller's response, and the evidence below. Your decision is final for the online case.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-5" x-data="{ decision: '{{ old('decision', 'refund') }}', waived: {{ old('return_waived') ? 'true' : 'false' }} }">@csrf @method('PATCH')
                            <fieldset>
                                <legend class="field-label">Final decision</legend>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <label class="cursor-pointer">
                                        <span class="flex h-full items-start gap-3 rounded-[10px] border border-line bg-white p-3.5 transition hover:border-blue-300 has-[:checked]:border-brand has-[:checked]:bg-blue-50/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                                            <input type="radio" name="decision" value="refund" x-model="decision" class="peer sr-only" @checked(old('decision', 'refund') === 'refund')>
                                            <span class="mt-0.5 size-4 shrink-0 rounded-full border border-slate-400 bg-white transition peer-checked:border-brand peer-checked:bg-brand" aria-hidden="true"></span>
                                            <span class="min-w-0"><span class="block text-sm font-semibold text-ink">Refund</span><span class="mt-0.5 block text-xs leading-5 text-muted">Award the buyer a refund.</span></span>
                                        </span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <span class="flex h-full items-start gap-3 rounded-[10px] border border-line bg-white p-3.5 transition hover:border-blue-300 has-[:checked]:border-brand has-[:checked]:bg-blue-50/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-2">
                                            <input type="radio" name="decision" value="replacement" x-model="decision" class="peer sr-only" @checked(old('decision') === 'replacement')>
                                            <span class="mt-0.5 size-4 shrink-0 rounded-full border border-slate-400 bg-white transition peer-checked:border-brand peer-checked:bg-brand" aria-hidden="true"></span>
                                            <span class="min-w-0"><span class="block text-sm font-semibold text-ink">Replacement</span><span class="mt-0.5 block text-xs leading-5 text-muted">Require the seller to ship again.</span></span>
                                        </span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <span class="flex h-full items-start gap-3 rounded-[10px] border border-line bg-white p-3.5 transition hover:border-red-300 has-[:checked]:border-danger has-[:checked]:bg-red-50/60 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-danger has-[:focus-visible]:ring-offset-2">
                                            <input type="radio" name="decision" value="deny" x-model="decision" class="peer sr-only" @checked(old('decision') === 'deny')>
                                            <span class="mt-0.5 size-4 shrink-0 rounded-full border border-slate-400 bg-white transition peer-checked:border-danger peer-checked:bg-danger" aria-hidden="true"></span>
                                            <span class="min-w-0"><span class="block text-sm font-semibold text-ink">Deny</span><span class="mt-0.5 block text-xs leading-5 text-muted">End the claim with reasons.</span></span>
                                        </span>
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('decision')" class="mt-2" />
                            </fieldset>
                            <div>
                                <label for="admin-decision-note" class="field-label">Reasoned decision</label>
                                <textarea id="admin-decision-note" name="note" required minlength="10" maxlength="2000" rows="4" class="field-control" placeholder="Explain the decision to both sides.">{{ old('note') }}</textarea>
                                <x-input-error :messages="$errors->get('note')" class="mt-2" />
                            </div>
                            <fieldset class="grid gap-4" x-show="decision !== 'deny'">
                                <legend class="field-label">Return of the goods</legend>
                                <label class="flex cursor-pointer items-start gap-3 rounded-[10px] border border-line bg-canvas px-3.5 py-3">
                                    <input type="checkbox" name="return_waived" value="1" x-model="waived" class="mt-0.5 rounded border-line text-brand focus:ring-brand">
                                    <span>
                                        <span class="block text-sm font-semibold text-ink">Waive the physical return</span>
                                        <span class="mt-0.5 block text-xs leading-5 text-muted">Set the remedy without asking for the goods back.</span>
                                    </span>
                                </label>
                                <div class="grid gap-4" x-show="! waived">
                                    <div>
                                        <label for="admin-return-instructions" class="field-label">Return instructions</label>
                                        <textarea id="admin-return-instructions" name="return_instructions" rows="3" maxlength="2000" x-bind:required="decision !== 'deny' && ! waived" class="field-control" placeholder="How and where to send the goods back.">{{ old('return_instructions') }}</textarea>
                                    </div>
                                    <div>
                                        <label for="admin-return-method" class="field-label">No-cost return method</label>
                                        <input id="admin-return-method" name="return_method" value="{{ old('return_method') }}" maxlength="500" x-bind:required="decision !== 'deny' && ! waived" class="field-control" placeholder="Seller-paid pickup or prepaid drop-off">
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('return_instructions')" />
                            </fieldset>
                            <div class="border-t border-line pt-5">
                                <button type="submit" name="action" value="decide" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2" @click="if (decision === 'deny' && ! window.confirm('Deny this claim? The claim ends with your recorded reasons.')) $event.preventDefault()">Record final decision</button>
                            </div>
                        </form>
                    @elseif ($role === 'admin' && $overdue && in_array($case->status, ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment', 'awaiting_confirmation'], true))
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Take over this overdue case</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">This case passed its deadline. Take it over to decide it yourself, or verify the recorded outcome if there is one.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5">@csrf @method('PATCH')
                            <button type="submit" name="action" value="takeover" class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Take over overdue case</button>
                        </form>
                        @if ($case->status === 'awaiting_confirmation' && $case->outcome)
                            <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-3 border-t border-line pt-5">@csrf @method('PATCH')
                                <div>
                                    <label for="admin-overdue-verify-note" class="field-label">Verification evidence and decision</label>
                                    <textarea id="admin-overdue-verify-note" name="note" required minlength="10" maxlength="2000" rows="3" class="field-control" placeholder="Summarise what the proof shows.">{{ old('note') }}</textarea>
                                    <x-input-error :messages="$errors->get('note')" class="mt-2" />
                                </div>
                                <button type="submit" name="action" value="verify" class="inline-flex min-h-11 w-fit items-center justify-center rounded-[10px] border border-green-300 bg-white px-5 text-sm font-semibold text-success transition hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-success focus:ring-offset-2">Verify completion</button>
                            </form>
                        @endif
                    @elseif ($role === 'admin' && $overdue && in_array($case->status, ['offered', 'rejected', 'awaiting_return'], true))
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Close the unanswered case</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">The buyer did not act before the deadline. Closing ends the case without a remedy.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-4">@csrf @method('PATCH')
                            <div>
                                <label for="admin-close-note" class="field-label">Reason for closing</label>
                                <textarea id="admin-close-note" name="note" required minlength="10" maxlength="2000" rows="3" class="field-control" placeholder="Record what was waited on and when it lapsed.">{{ old('note') }}</textarea>
                                <x-input-error :messages="$errors->get('note')" class="mt-2" />
                            </div>
                            <button type="submit" name="action" value="close" class="inline-flex min-h-11 w-fit items-center justify-center rounded-[10px] border border-red-200 bg-white px-5 text-sm font-semibold text-danger transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2" x-data @click="if (! window.confirm('Close this case without a remedy? The buyer\'s claim ends and cannot be reopened online.')) $event.preventDefault()">Close unanswered case</button>
                        </form>
                    @elseif ($role === 'buyer' && $case->status === 'awaiting_seller' && $overdue)
                        <h2 id="next-step-heading" class="text-base font-semibold text-ink">Ask JMMart to step in</h2>
                        <p class="mt-1.5 text-sm leading-6 text-muted">The seller did not respond in time. JMMart can decide the case instead.</p>
                        <form method="POST" action="{{ $actionRoute }}" class="mt-5 grid gap-4">@csrf @method('PATCH')
                            <div>
                                <label for="buyer-escalate-note" class="field-label">Why should support step in?</label>
                                <textarea id="buyer-escalate-note" name="note" required minlength="10" maxlength="2000" rows="3" class="field-control" placeholder="Explain how the seller missed the deadline.">{{ old('note') }}</textarea>
                                <x-input-error :messages="$errors->get('note')" class="mt-2" />
                            </div>
                            <button type="submit" name="action" value="appeal" class="inline-flex min-h-11 w-fit items-center justify-center rounded-[10px] bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Ask JMMart to step in</button>
                        </form>
                    @elseif ($isTerminal)
                        @php
                            $closedCopy = match ($case->status) {
                                'completed' => 'This case is complete. Email support with the order number if something is still outstanding.',
                                'denied' => 'JMMart denied this claim. Email support with the order number if you have new evidence.',
                                default => 'This case was closed. Email support with the order number if something is still outstanding.',
                            };
                            $closedIconClasses = match ($case->status) {
                                'completed' => 'bg-green-50 text-success',
                                'denied' => 'bg-red-50 text-danger',
                                default => 'bg-slate-100 text-muted',
                            };
                        @endphp
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-[10px] {{ $closedIconClasses }}" aria-hidden="true">
                                @if ($case->status === 'completed')
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6" /></svg>
                                @else
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
                                @endif
                            </span>
                            <div>
                                <h2 id="next-step-heading" class="text-base font-semibold text-ink">No action pending</h2>
                                <p class="mt-1 text-sm leading-6 text-muted">{{ $closedCopy }}</p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-brand-hover" aria-hidden="true">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                            </span>
                            <div>
                                <h2 id="next-step-heading" class="text-base font-semibold text-ink">Waiting for the next step</h2>
                                <p class="mt-1 text-sm leading-6 text-muted">The other side is on the clock. JMMart steps in once the deadline above passes.</p>
                            </div>
                        </div>
                    @endif
                </section>

                <section class="section-card" aria-labelledby="request-heading">
                    <h2 id="request-heading" class="text-base font-semibold text-ink">Request summary</h2>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-canvas px-2.5 py-1 text-xs font-semibold text-muted ring-1 ring-inset ring-line">{{ $reasonLabel }}</span>
                        <span class="rounded-full bg-canvas px-2.5 py-1 text-xs font-semibold text-muted ring-1 ring-inset ring-line">Preferred: {{ ucfirst($case->requested_remedy) }}</span>
                        @if ($case->remedy)
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-brand-hover ring-1 ring-inset ring-blue-200">Approved: {{ ucfirst($case->remedy) }}</span>
                        @endif
                    </div>
                    <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-ink">{{ $case->details }}</p>

                    <div class="mt-4 table-scroll">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-muted">
                                    <th scope="col" class="py-2 pr-3 font-semibold">Item</th>
                                    <th scope="col" class="py-2 pr-3 text-right font-semibold">Qty</th>
                                    <th scope="col" class="py-2 pr-3 text-right font-semibold">Unit price</th>
                                    <th scope="col" class="py-2 text-right font-semibold">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($case->items as $item)
                                    <tr>
                                        <td class="py-2.5 pr-3">
                                            <span class="font-medium text-ink">{{ $item->orderItem->product_name }}</span>
                                            @if ($role === 'seller' && $item->restocked_quantity > 0)
                                                <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-muted">{{ $item->restocked_quantity }} restocked</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 pr-3 text-right tabular-nums text-ink">{{ $item->quantity }}</td>
                                        <td class="py-2.5 pr-3 text-right tabular-nums text-muted">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="py-2.5 text-right tabular-nums text-ink">₱{{ number_format((float) bcmul((string) $item->unit_price, (string) $item->quantity, 2), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-line">
                                    <td colspan="3" class="py-2.5 pr-3 text-sm font-semibold text-ink">Amount at stake</td>
                                    <td class="py-2.5 text-right text-sm font-bold tabular-nums text-ink">₱{{ number_format((float) $amountAtStake, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if ($case->response_note)
                        <div class="mt-4 rounded-[10px] border border-line bg-canvas px-3.5 py-3">
                            <p class="text-xs font-bold uppercase tracking-[0.1em] text-muted">Latest response</p>
                            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-6 text-ink">{{ $case->response_note }}</p>
                        </div>
                    @endif
                    @if ($case->remedy && $case->remedy !== $case->requested_remedy)
                        <p class="mt-3 flex items-start gap-2 rounded-[10px] border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-sm text-warning-text">
                            <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                            The approved remedy ({{ $case->remedy }}) differs from the {{ $case->requested_remedy }} the buyer asked for.
                        </p>
                    @endif
                    @if ($case->return_waived)
                        <p class="mt-3 text-sm text-muted">Physical return waived — no goods need to travel back.</p>
                    @elseif ($case->return_instructions)
                        <div class="mt-3 rounded-[10px] border border-line bg-canvas px-3.5 py-3">
                            <p class="text-xs font-bold uppercase tracking-[0.1em] text-muted">Return instructions</p>
                            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-6 text-ink">{{ $case->return_instructions }}</p>
                            <p class="mt-1 text-sm text-muted">No-cost method: {{ $case->return_method }}</p>
                        </div>
                    @endif
                    @if ($case->outcome?->kind === 'refund')
                        <div class="mt-3 rounded-[10px] border border-green-200 bg-green-50 px-3.5 py-3">
                            <p class="text-xs font-bold uppercase tracking-[0.1em] text-success">Refund recorded</p>
                            <p class="mt-1.5 text-sm text-ink">₱{{ number_format((float) $case->outcome->amount, 2) }} · paid {{ $case->outcome->paid_at?->format('M j, Y') }}. The seller's payment proof is in the evidence list.</p>
                        </div>
                    @endif
                    @if ($case->outcome?->kind === 'replacement')
                        <div class="mt-3 rounded-[10px] border border-green-200 bg-green-50 px-3.5 py-3">
                            <p class="text-xs font-bold uppercase tracking-[0.1em] text-success">Replacement shipment</p>
                            <p class="mt-1.5 text-sm text-ink">Reference {{ $case->outcome->shipment_reference }} · shipped {{ $case->outcome->shipped_at?->format('M j, Y') }}.</p>
                        </div>
                    @endif
                </section>

                <section class="section-card" aria-labelledby="evidence-heading">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="evidence-heading" class="text-base font-semibold text-ink">Private evidence</h2>
                        <span class="text-xs text-muted">{{ $case->evidence->count() }} {{ Str::plural('file', $case->evidence->count()) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-muted">Visible to the buyer, the seller, and JMMart support only.</p>
                    <ul class="mt-3 divide-y divide-line">
                        @forelse ($case->evidence as $evidence)
                            @php
                                $extension = strtolower(pathinfo($evidence->original_name, PATHINFO_EXTENSION));
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
                                $kindLabel = match ($evidence->kind) {
                                    'problem' => 'Reported issue',
                                    'handoff' => 'Return handoff',
                                    'refund' => 'Refund proof',
                                    default => ucfirst($evidence->kind),
                                };
                            @endphp
                            <li class="flex items-center gap-3 py-3">
                                @if ($isImage)
                                    <img src="{{ route('returns.evidence', [$case, $evidence]) }}" alt="{{ $kindLabel }}: {{ $evidence->original_name }}" loading="lazy" decoding="async" class="h-16 w-16 shrink-0 rounded-[10px] border border-line object-cover">
                                @else
                                    <span class="grid h-16 w-16 shrink-0 place-items-center rounded-[10px] border border-line bg-canvas text-muted" aria-hidden="true">
                                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h7l4 4v14H5V3z" /><path d="M15 3v5h5" /></svg>
                                    </span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('returns.evidence', [$case, $evidence]) }}" @if ($isImage) target="_blank" rel="noopener noreferrer" @endif class="break-all text-sm font-semibold text-brand-hover underline decoration-slate-300 underline-offset-4 hover:text-brand">{{ $evidence->original_name }}</a>
                                    <p class="mt-0.5 text-xs text-muted">{{ $kindLabel }} · uploaded by {{ $evidence->uploader?->name ?? 'Unknown' }} · {{ $evidence->created_at->format('M j, Y · g:i A') }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="py-3 text-sm text-muted">No files added. Photos and documents uploaded to this case appear here.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="section-card" aria-labelledby="history-heading">
                    <h2 id="history-heading" class="text-base font-semibold text-ink">Case history</h2>
                    <p class="mt-1 text-xs text-muted">Every recorded step, newest last.</p>
                    <div class="mt-4">
                        <x-returns.event-timeline :case="$case" />
                    </div>
                </section>
            </div>

            <aside class="min-w-0 space-y-5">
                <section class="section-card" aria-labelledby="case-details-heading">
                    <h2 id="case-details-heading" class="text-base font-semibold text-ink">Case details</h2>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted">Order</dt>
                            <dd class="text-right font-semibold">
                                <a class="text-brand-hover underline decoration-slate-300 underline-offset-4 hover:text-brand" href="{{ $role === 'admin' ? route('admin.orders.show', $case->sellerOrder->order) : ($role === 'seller' ? route('seller.orders.show', $case->sellerOrder) : route('orders.show', $case->sellerOrder->order)) }}">{{ $case->sellerOrder->order->number }}</a>
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted">Store</dt>
                            <dd class="text-right font-semibold text-ink">{{ $case->sellerOrder->store->name }}</dd>
                        </div>
                        @if ($role !== 'buyer')
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-muted">Buyer</dt>
                                <dd class="text-right font-semibold text-ink">{{ $case->buyer->name }}</dd>
                            </div>
                        @endif
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted">Opened</dt>
                            <dd class="text-right font-semibold text-ink">
                                {{ $case->created_at->format('M j, Y') }}
                                <span class="block text-xs font-normal text-muted">by {{ $case->opened_by === $case->buyer_id ? 'the buyer' : 'JMMart support' }}</span>
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted">Deadline</dt>
                            <dd class="text-right font-semibold text-ink">
                                @if ($deadline)
                                    <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('M j, Y · g:i A') }}</time>
                                @else
                                    None
                                @endif
                            </dd>
                        </div>
                        @if ($role !== 'buyer')
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-muted">Cash collection</dt>
                                <dd class="text-right font-semibold {{ $case->sellerOrder->cod_collected_at ? 'text-ink' : 'text-warning-text' }}">{{ $case->sellerOrder->cod_collected_at ? 'Collected '.$case->sellerOrder->cod_collected_at->format('M j, Y') : 'Not recorded' }}</dd>
                            </div>
                        @endif
                        <div class="flex items-start justify-between gap-3 border-t border-line pt-3">
                            <dt class="text-muted">Items in case</dt>
                            <dd class="text-right font-semibold text-ink">{{ $unitsInCase }} {{ Str::plural('unit', $unitsInCase) }} · ₱{{ number_format((float) $amountAtStake, 2) }}</dd>
                        </div>
                    </dl>
                </section>

                @if ($role === 'seller' && $case->received_at)
                    <section class="section-card" aria-labelledby="restock-heading">
                        <h2 id="restock-heading" class="text-base font-semibold text-ink">Inspection &amp; restocking</h2>
                        <p class="mt-1 text-xs text-muted">Mark the resellable units so they return to your stock.</p>
                        @foreach ($case->items as $item)
                            @if ($item->quantity > $item->restocked_quantity)
                                <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-2">@csrf @method('PATCH')
                                    <input type="hidden" name="case_item_id" value="{{ $item->id }}">
                                    <p class="text-sm text-ink">{{ $item->orderItem->product_name }} <span class="text-muted">· {{ $item->quantity - $item->restocked_quantity }} of {{ $item->quantity }} not yet restocked</span></p>
                                    <div class="flex items-end gap-3">
                                        <label class="min-w-0 text-sm">
                                            <span class="field-label">Quantity to restock</span>
                                            <input type="number" name="quantity" min="1" max="{{ $item->quantity - $item->restocked_quantity }}" required class="field-control w-24">
                                        </label>
                                        <button type="submit" name="action" value="restock" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-line bg-white px-4 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Mark resellable</button>
                                    </div>
                                </form>
                            @endif
                        @endforeach
                    </section>
                @endif

                <section class="section-card" aria-labelledby="help-heading">
                    <h2 id="help-heading" class="text-base font-semibold text-ink">Get help</h2>
                    @if ($role === 'buyer')
                        <p class="mt-1 text-sm leading-6 text-muted">Questions about the goods or the return? Message the store directly. Keep bank details out of chat.</p>
                        <form method="POST" action="{{ route('messages.seller-orders.start', $case->sellerOrder) }}" class="mt-3">@csrf
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] border border-brand bg-white px-4 text-sm font-semibold text-brand-hover transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Message {{ $case->sellerOrder->store->name }}</button>
                        </form>
                    @elseif ($role === 'seller')
                        <p class="mt-1 text-sm leading-6 text-muted">Keep the conversation on record. Bank or refund destination details stay out of chat, reviews, and case notes.</p>
                        <a href="{{ route('messages.index') }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-brand underline decoration-slate-300 underline-offset-4 hover:text-brand">Message the buyer in your inbox</a>
                    @else
                        <p class="mt-1 text-sm leading-6 text-muted">Refund destinations are coordinated privately, outside the app. Never record bank details in case notes.</p>
                    @endif
                    <div class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                        <p class="text-muted">JMMart support: <a class="font-semibold text-brand-hover underline decoration-slate-300 underline-offset-4 hover:text-brand" href="mailto:itservices@jmsolutionitservices.com?subject=Return%20case%20{{ urlencode($case->sellerOrder->order->number) }}">itservices@jmsolutionitservices.com</a></p>
                        <p><a class="font-semibold text-brand-hover underline decoration-slate-300 underline-offset-4 hover:text-brand" href="{{ route('returns') }}">Return and refund policy</a></p>
                    </div>
                </section>
            </aside>
        </div>
    </div></div>
</x-app-layout>
