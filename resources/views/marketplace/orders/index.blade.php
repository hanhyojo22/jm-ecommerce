<x-app-layout>
    @php
        $statusStyles = [
            'pending' => ['Awaiting store', 'bg-muted', 'text-ink'],
            'confirmed' => ['Preparing', 'bg-muted', 'text-ink'],
            'processing' => ['Preparing', 'bg-muted', 'text-ink'],
            'shipped' => ['On the way', 'bg-brand', 'text-ink'],
            'delivered' => ['Delivered', 'bg-success', 'text-success'],
            'cancelled' => ['Cancelled', 'bg-danger', 'text-danger-text'],
        ];
        $emptyCopy = [
            'all' => ['Your first purchase starts here', 'Your orders and delivery updates will appear here.'],
            'to_pay' => ['Nothing to pay', 'Orders you pay a store for directly, by GCash, Maya, or bank transfer, wait here until you send the payment.'],
            'awaiting_confirmation' => ['Nothing waiting on a store', 'Orders a store has not confirmed yet appear here.'],
            'to_dispatch' => ['Nothing being prepared', 'Orders a store has confirmed and is getting ready to ship appear here.'],
            'to_receive' => ['Nothing on the way', 'Shipped orders appear here until you confirm they arrived.'],
            'delivered' => ['No delivered orders yet', 'Orders appear here once they arrive.'],
            'to_review' => ['You’re all caught up', 'Delivered items you haven’t reviewed appear here.'],
            'returns' => ['No returns or refund requests', 'Open a return from a delivered order within '.(int) config('marketplace.orders.return_window_days').' days of delivery. Requests you open appear here.'],
        ];
        $search = trim($filters['search'] ?? '');
        $hasFilters = $search !== '' || filled($filters['status'] ?? null);
        $returnDays = (int) config('marketplace.orders.return_window_days');
        $returnDecisions = ['offered', 'rejected', 'awaiting_confirmation', 'awaiting_return'];
        $waitingOnYou = array_diff_key($attentionCounts, ['to_receive' => true]);
        $attentionLabel = fn (string $key, int $count): string => match ($key) {
            'to_pay' => $count.' to pay',
            'returns' => $count.' '.Str::plural('return', $count).' '.($count === 1 ? 'needs' : 'need').' you',
            'refunds' => $count.' '.Str::plural('refund', $count).' to chase',
        };
        $attentionHref = fn (string $key): string => match ($key) {
            'to_pay' => route('orders.index', ['tab' => 'to_pay']),
            'returns' => route('orders.index', ['tab' => 'returns']),
            // Refunds due already lead the All list, so the link only has to reach it.
            'refunds' => '#purchases',
        };
        // Compact sizes only where a fine pointer is likely; phones and tablets keep 44px targets.
        $quietAction = 'inline-flex min-h-11 items-center rounded-md px-1.5 text-meta font-semibold text-brand underline-offset-2 transition-colors hover:text-brand-hover hover:underline lg:min-h-10';
        $primaryAction = 'inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-3.5 text-meta font-semibold text-white transition-colors hover:bg-brand-hover lg:min-h-10';
        $outlineAction = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-3.5 text-meta font-semibold text-ink transition-colors hover:border-brand hover:text-brand-hover lg:min-h-10';
        $inlineLink = 'font-semibold text-brand underline-offset-2 hover:text-brand-hover hover:underline';
        $railToneClasses = [
            'brand' => 'bg-brand',
            'warning' => 'bg-warning',
            'success' => 'bg-success',
            'muted' => 'bg-muted',
        ];
    @endphp

    <x-marketplace.account-shell active="orders">
        <div data-purchases class="min-w-0">
            <header class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                <div class="min-w-0">
                    <h1 class="text-3xl font-semibold tracking-tight text-ink">My purchases</h1>
                    @if ($sellerOrders->total() > 0)
                        <p class="mt-1 text-meta text-muted">
                            {{ $sellerOrders->total() }} {{ Str::plural('order', $sellerOrders->total()) }}
                            @if ($attentionCounts !== [] && $tab === 'all' && ! $hasFilters)
                                <span aria-hidden="true" class="px-1.5 text-line">·</span>
                                @if ($waitingOnYou !== [])
                                    <span class="font-semibold text-ink">Waiting on you:</span>
                                    @foreach ($waitingOnYou as $key => $count)
                                        <a href="{{ $attentionHref($key) }}" class="{{ $inlineLink }}">{{ $attentionLabel($key, $count) }}</a>@unless ($loop->last)<span aria-hidden="true" class="text-muted"> · </span>@endunless
                                    @endforeach
                                @endif
                                @isset ($attentionCounts['to_receive'])
                                    @if ($waitingOnYou !== [])<span aria-hidden="true" class="text-muted"> · </span>@endif
                                    <a href="{{ route('orders.index', ['tab' => 'to_receive']) }}" class="{{ $inlineLink }}">{{ $attentionCounts['to_receive'] }} on the way</a> to you
                                @endisset
                            @endif
                        </p>
                    @endif
                </div>
                <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center gap-1 text-meta font-semibold text-brand underline-offset-2 hover:text-brand-hover hover:underline lg:min-h-10">Continue shopping <span aria-hidden="true">→</span></a>
            </header>

            @if (session('status'))
                <p role="status" class="mt-3 rounded-[10px] border border-[var(--color-success-line)] bg-[var(--color-success-wash)] px-3.5 py-2.5 text-meta font-medium text-success">{{ session('status') }}</p>
            @endif
            @error('received')
                <p role="alert" class="mt-3 rounded-[10px] border border-[var(--color-danger-line)] bg-[var(--color-danger-wash)] px-3.5 py-2.5 text-meta font-medium text-danger-text">{{ $message }}</p>
            @enderror

            <div class="mt-4 flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                {{-- The rail scrolls on a phone, so the selected tab and whichever tab takes focus are kept in view. --}}
                <nav class="marketplace-nav-scroll relative flex min-w-0 gap-0.5 overflow-x-auto border-b border-line xl:flex-1" aria-label="Purchase status filters" x-data @focusin="$event.target.scrollIntoView({ block: 'nearest', inline: 'center' })">
                    @foreach ($tabs as $key => $label)
                        <a href="{{ route('orders.index', [...request()->except('tab', 'page'), 'tab' => $key]) }}" @class([
                            'relative inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap px-2 text-meta font-semibold transition-colors first:pl-0 focus-visible:outline-offset-[-2px]',
                            'text-ink after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 after:rounded-full after:bg-brand first:after:left-0' => $tab === $key,
                            'text-muted hover:text-ink' => $tab !== $key,
                        ]) @if ($tab === $key) aria-current="page" x-init="$el.parentElement.scrollLeft = Math.max(0, $el.offsetLeft - 24)" @endif>
                            {{ $label }}@if ($tabCounts[$key] > 0 || $tab === $key) <span @class(['text-[10px] font-semibold tabular-nums', 'text-brand-hover' => $tab === $key])>{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}</span></span>@endif
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ route('orders.index') }}" class="flex min-w-0 items-start gap-2 xl:w-[280px] xl:shrink-0">
                    @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                    @if (filled($filters['status'] ?? null))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <div class="min-w-0 flex-1">
                        <label for="purchase-search" class="sr-only">Search purchases</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                            <input id="purchase-search" name="search" value="{{ $search }}" type="search" maxlength="100" enterkeyhint="search" class="field-control pl-9 text-[16px] sm:text-meta lg:min-h-10 {{ $search !== '' ? 'pr-11' : '' }}" placeholder="Order #, store, or product" @error('search') aria-invalid="true" aria-describedby="purchase-search-error" @enderror>
                            @if ($search !== '')
                                <a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="absolute right-0 top-0 grid size-11 place-items-center rounded-lg text-muted hover:text-ink lg:size-10" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></a>
                            @endif
                        </div>
                        <x-input-error id="purchase-search-error" :messages="$errors->get('search')" class="mt-1.5" />
                    </div>
                    <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-line bg-surface px-3.5 text-meta font-semibold text-ink transition-colors hover:border-brand hover:text-brand-hover lg:min-h-10">Search</button>
                </form>
            </div>
            @if (filled($filters['status'] ?? null) && isset($statusStyles[$filters['status']]))
                <p class="mt-2 text-meta text-muted">Filtered to checkouts marked {{ $statusStyles[$filters['status']][0] }} <span aria-hidden="true">·</span> <a href="{{ route('orders.index', request()->except('status', 'page')) }}" class="{{ $inlineLink }}">Show all</a></p>
            @endif
            <x-input-error :messages="$errors->get('tab')" class="mt-2" />
            <x-input-error :messages="$errors->get('status')" class="mt-2" />

            <section id="purchases" class="mt-4 grid scroll-mt-24 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3" aria-labelledby="purchase-results-heading">
                <h2 id="purchase-results-heading" class="sr-only">{{ $tabs[$tab] }} purchases{{ $search !== '' ? ' matching “'.$search.'”' : '' }} ({{ $sellerOrders->total() }})</h2>

                @forelse ($sellerOrders as $sellerOrder)
                    @php
                        $order = $sellerOrder->order;
                        $store = $sellerOrder->store->name;
                        $returnIssue = $sellerOrder->issues->firstWhere('type', 'return_refund');
                        $cancelRequest = $sellerOrder->issues->first(fn ($issue): bool => $issue->type === 'cancellation' && $issue->status === 'open');
                        $cancelledAtRequest = $sellerOrder->status === 'cancelled' && $sellerOrder->issues->contains(fn ($issue): bool => $issue->type === 'cancellation' && $issue->status === 'resolved');
                        $returnCases = $sellerOrder->returnCases;
                        $returnCase = $returnCases->first();
                        // A missed deadline flips the turn: a late store can be escalated, a late buyer may see the case closed.
                        $returnOverdue = $returnCase?->isOverdue() ?? false;
                        $storeLate = $returnOverdue && $returnCase->status === 'awaiting_seller';
                        // Only answering an offer or rejection has a hard buyer cutoff; a late send-back can still be recorded.
                        $buyerLate = $returnOverdue && in_array($returnCase->status, ['offered', 'rejected'], true);
                        $storeMissed = $returnOverdue && in_array($returnCase->status, ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment'], true);
                        $returnDecision = ($returnCase && in_array($returnCase->status, $returnDecisions, true) && ! $buyerLate) || $storeLate;
                        $returnFinished = $returnCase && in_array($returnCase->status, ['completed', 'denied', 'closed'], true);
                        $returnOpen = $returnCases->isNotEmpty() || ($returnIssue && ($tab === 'returns' || $returnIssue->status === 'open'));
                        $reviewableItemId = $reviewableItemIds[$sellerOrder->id] ?? null;
                        $method = $order->payment?->method;
                        $isDirect = in_array($method, \App\Models\Payment::DIRECT_METHODS, true);
                        $needsPayment = $isDirect && $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null && $sellerOrder->payment_reference === null;
                        // The same deadline orders:cancel-unpaid enforces, so the buyer sees it before it passes.
                        $payHours = config('marketplace.wallet.payment_hours.'.$method);
                        $payBy = $needsPayment && $payHours ? $order->created_at->copy()->addHours((int) $payHours) : null;
                        // Money already sent to a store that then cancelled is owed back by that store.
                        $refundDue = $sellerOrder->status === 'cancelled' && $isDirect && $sellerOrder->refund_reference === null && ($sellerOrder->payment_reference !== null || $sellerOrder->cod_collected_at !== null);
                        $paymentState = match (true) {
                            $sellerOrder->status === 'cancelled' => $sellerOrder->refund_reference ? 'Refunded' : ($refundDue ? 'Refund due' : null),
                            ! $isDirect => $sellerOrder->cod_collected_at ? 'Paid' : 'Pay on delivery',
                            $sellerOrder->cod_collected_at !== null => 'Paid',
                            $sellerOrder->payment_reference !== null => 'Reference sent',
                            default => 'To pay',
                        };
                        $returnEndsAt = $sellerOrder->status === 'delivered' ? $sellerOrder->delivered_at?->copy()->addDays($returnDays) : null;
                        $canReturn = $returnEndsAt && now()->lessThanOrEqualTo($returnEndsAt);
                        // The closed-window note fades out after two weeks so old history isn't a wall of the same line.
                        $returnClosedRecently = $returnEndsAt && ! $canReturn && $returnEndsAt->diffInDays(now()) <= 14;
                        $returnSoon = $canReturn && ($returnEndsAt->isToday() || $returnEndsAt->isTomorrow());
                        [$statusLabel, $statusDot, $statusText] = $statusStyles[$sellerOrder->status];
                        if ($needsPayment) {
                            [$statusLabel, $statusDot, $statusText] = ['To pay', 'bg-warning', 'text-warning-text'];
                        } elseif ($cancelRequest) {
                            [$statusLabel, $statusDot, $statusText] = ['Cancel requested', 'bg-warning', 'text-warning-text'];
                        }
                        $statusDate = match ($sellerOrder->status) {
                            'shipped' => $sellerOrder->shipped_at,
                            'delivered' => $sellerOrder->delivered_at,
                            default => null,
                        };
                        $statusDate = $statusDate?->isSameDay($order->created_at) ? null : $statusDate;
                        $placedFormat = $order->created_at->isCurrentYear() ? 'M j' : 'M j, Y';
                        // One filled button per order, and only when the buyer has something to do.
                        $nextAction = match (true) {
                            $needsPayment => [$payBy?->isPast() ? 'Send reference' : 'Pay store', route('orders.show', $order).'#pay-stores'],
                            $sellerOrder->status === 'shipped' => ['Order received', null],
                            $storeLate => ['Ask JMMart to step in', route('returns.show', $returnCase)],
                            $returnDecision => [match ($returnCase->status) { 'awaiting_return' => 'Send item back', 'awaiting_confirmation' => 'Confirm outcome', default => 'Answer return' }, route('returns.show', $returnCase)],
                            (bool) $reviewableItemId => ['Write review', route('orders.show', $order).'#review-item-'.$reviewableItemId],
                            default => null,
                        };
                        $alsoReview = $reviewableItemId && $nextAction && $nextAction[0] !== 'Write review';
                        // A return the store or JMMart is handling stays reachable, but not as the next step.
                        $viewReturnHref = match (true) {
                            $returnDecision => null,
                            $returnCase !== null => route('returns.show', $returnCase),
                            (bool) $returnOpen => route('orders.show', $order).'#order-help',
                            default => null,
                        };
                        $items = $sellerOrder->items;
                        $mediaItem = $items->first(fn ($item): bool => $item->image_path !== null || $item->product?->thumbnail !== null);
                        $mediaPath = $mediaItem?->image_path ?? $mediaItem?->product?->thumbnail?->path;
                        $shortNumber = Str::upper(Str::substr($order->number, -6));
                        $railStep = match (true) {
                            $sellerOrder->status === 'delivered' => 4,
                            $sellerOrder->status === 'shipped' => 3,
                            in_array($sellerOrder->status, ['confirmed', 'processing'], true) => 2,
                            $sellerOrder->status === 'pending' => 1,
                            default => 0,
                        };
                        $railTone = match (true) {
                            $railStep === 0 => 'muted',
                            $sellerOrder->status === 'delivered' => 'success',
                            $needsPayment || $returnDecision => 'warning',
                            default => 'brand',
                        };
                        // The image badge is the order's own state; a return case reads as a second badge below it.
                        $overlayLabel = $statusLabel;
                        $overlayDot = $statusDot;
                        $overlayText = $statusText;
                        if ($buyerLate) {
                            [$overlayLabel, $overlayDot, $overlayText] = ['Response window passed', 'bg-muted', 'text-muted'];
                        } elseif ($storeMissed) {
                            [$overlayLabel, $overlayDot, $overlayText] = [$storeLate ? 'Store missed its deadline' : 'Store is running late', $storeLate ? 'bg-warning' : 'bg-muted', $storeLate ? 'text-warning-text' : 'text-ink'];
                        }
                        $noteIsUrgent = $needsPayment || $returnSoon || $refundDue || $returnDecision;
                    @endphp
                    <article class="group flex min-w-0 flex-col overflow-hidden rounded-2xl border border-line bg-surface transition-shadow hover:shadow-[0_12px_32px_-24px_rgba(24,44,72,0.55)]" aria-labelledby="purchase-{{ $sellerOrder->id }} purchase-number-{{ $sellerOrder->id }}">
                        <div class="relative aspect-[5/2] shrink-0 overflow-hidden bg-canvas">
                            @if ($mediaPath)
                                <img src="{{ Storage::disk('product_images')->url($mediaPath) }}" alt="" width="640" height="256" loading="lazy" class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]">
                            @else
                                <div class="grid size-full place-items-center text-muted">
                                    <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                                    <span class="sr-only">Product image unavailable</span>
                                </div>
                            @endif
                            <span class="absolute left-3 top-3 inline-flex max-w-[calc(100%-5.5rem)] items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 text-[11px] font-semibold shadow-sm ring-1 ring-black/5">
                                <span class="size-1.5 shrink-0 rounded-full {{ $overlayDot }}" aria-hidden="true"></span>
                                <span @class(['truncate', $overlayText])>{{ $overlayLabel }}</span>
                                @if ($statusDate)<span class="font-medium text-muted"><span aria-hidden="true">·</span> {{ $statusDate->format('M j') }}</span>@endif
                            </span>
                            <span class="absolute right-3 top-3 rounded-full bg-ink/80 px-2 py-1 text-[11px] font-semibold text-white backdrop-blur-sm">{{ $items->count() }} {{ Str::plural('item', $items->count()) }}</span>
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    @if ($sellerOrder->store->logo_path)
                                        <img src="{{ Storage::disk('public')->url($sellerOrder->store->logo_path) }}" alt="" width="16" height="16" class="size-4 shrink-0 rounded object-cover">
                                    @else
                                        <svg class="size-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 9h16v11H4zM3 9l1.5-5h15L21 9M9 20v-6h6v6"/></svg>
                                    @endif
                                    <h3 id="purchase-{{ $sellerOrder->id }}" class="min-w-0 truncate text-sm font-semibold text-ink"><a href="{{ route('catalog.store', $sellerOrder->store->slug) }}" class="block py-0.5 hover:text-brand-hover">{{ $store }}</a></h3>
                                </div>
                                <p class="shrink-0 text-[11px] leading-4 text-muted">
                                    <a id="purchase-number-{{ $sellerOrder->id }}" href="{{ route('orders.show', $order) }}" class="inline-flex min-h-6 items-center font-medium tabular-nums text-ink underline-offset-2 hover:underline"><span aria-hidden="true">#…{{ $shortNumber }}</span><span class="sr-only">Order {{ $order->number }}</span></a>
                                    <span aria-hidden="true">·</span> <time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format($placedFormat) }}</time>
                                </p>
                            </div>

                            @if (! $buyerLate && ! $storeMissed && $returnCase && ($tab === 'returns' || ! $returnFinished))
                                <x-returns.status-badge :status="$returnCase->status" buyer class="mt-1.5 self-start" />
                            @elseif ($tab === 'returns' && $returnIssue)
                                <p class="mt-1.5 inline-flex self-start items-center gap-1.5 text-[11px] font-semibold text-warning-text"><span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Return request open</p>
                            @endif

                            <div x-data="{ more: false }" class="mt-3">
                                <ul class="space-y-1.5" aria-label="Items in this order">
                                    @foreach ($items as $item)
                                        <li @if ($items->count() > 4 && $loop->index >= 3) x-show="more" x-cloak @endif class="flex min-w-0 items-baseline gap-2 text-xs">
                                            <div class="min-w-0 flex-1">
                                                @if ($item->product && $item->product->store)
                                                    <a href="{{ route('catalog.show', [$item->product->store->slug, $item->product->slug]) }}" class="break-words font-medium leading-4 text-ink underline-offset-2 hover:text-brand-hover hover:underline">{{ $item->product_name }}</a>
                                                @else
                                                    <p class="break-words font-medium leading-4 text-ink">{{ $item->product_name }}</p>
                                                @endif
                                                @if ($item->variant_label)
                                                    <p class="mt-0.5 break-words text-[11px] leading-4 text-muted">{{ $item->variant_label }}</p>
                                                @endif
                                            </div>
                                            <span class="shrink-0 text-[11px] tabular-nums text-muted">×{{ $item->quantity }}</span>
                                            <span class="shrink-0 text-[11px] font-semibold tabular-nums text-ink">₱{{ number_format($item->line_total, 2) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($items->count() > 4)
                                    <button type="button" @click="more = ! more" :aria-expanded="more.toString()" aria-expanded="false" class="{{ $quietAction }} -ml-1.5 mt-1" x-text="more ? 'Show fewer items' : 'Show {{ $items->count() - 3 }} more {{ Str::plural('item', $items->count() - 3) }}'">Show {{ $items->count() - 3 }} more {{ Str::plural('item', $items->count() - 3) }}</button>
                                @endif
                            </div>

                            <div class="mt-3 flex items-center gap-1" aria-hidden="true">
                                @for ($step = 1; $step <= 4; $step++)
                                    @php
                                        $segmentClass = match (true) {
                                            $step > $railStep => 'bg-line',
                                            $railTone === 'success' => 'bg-success',
                                            $railTone === 'brand' => 'bg-brand',
                                            $step === $railStep => $railToneClasses[$railTone],
                                            default => 'bg-brand',
                                        };
                                    @endphp
                                    <span class="h-0.5 flex-1 rounded-full {{ $segmentClass }}"></span>
                                @endfor
                            </div>

                            @unless ($needsPayment && $payBy?->isPast())
                                <p @class(['mt-2 text-meta leading-5', 'text-warning-text' => $noteIsUrgent, 'text-muted' => ! $noteIsUrgent])>
                                    @if ($refundDue)
                                        This order was cancelled after you paid. {{ $store }} owes you a refund of ₱{{ number_format($sellerOrder->total, 2) }}. Message them first. If the refund doesn’t arrive, <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">get help from JMMart</a>.
                                    @elseif ($sellerOrder->status === 'cancelled')
                                        {{ $cancelledAtRequest ? 'Cancelled at your request.' : 'This order was cancelled.' }}@if ($sellerOrder->refund_reference) Refunded (ref. {{ $sellerOrder->refund_reference }}).@endif
                                    @elseif ($cancelRequest)
                                        You asked {{ $store }} to cancel. Its reply will show on the <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">order page</a>.
                                    @elseif ($needsPayment)
                                        Pay {{ $store }} ₱{{ number_format($sellerOrder->total, 2) }} by {{ \App\Models\Payment::METHOD_LABELS[$method] }}@if ($payBy) before {{ $payBy->format('M j, g:i A') }}{{ now()->diffInHours($payBy) < 24 ? ' ('.$payBy->diffForHumans(['parts' => 1]).')' : '' }}@endif, then send the reference number. Unpaid orders are cancelled. <a href="{{ route('terms') }}#wallet-payments" class="{{ $inlineLink }}">How paying a store works</a>
                                    @elseif ($isDirect && $sellerOrder->status === 'pending')
                                        Reference sent. {{ $store }} confirms once the payment shows in its account. <a href="{{ route('terms') }}#wallet-payments" class="{{ $inlineLink }}">How paying a store works</a>
                                    @elseif ($sellerOrder->status === 'pending')
                                        Waiting for {{ $store }} to confirm your order. <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">Need to cancel?</a>
                                    @elseif (in_array($sellerOrder->status, ['confirmed', 'processing'], true))
                                        {{ $store }} is preparing your order for shipping.@if ($sellerOrder->status === 'confirmed') <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">Need to cancel?</a>@endif
                                    @elseif ($sellerOrder->status === 'shipped')
                                        On its way. Confirm with Order received once it arrives{{ $sellerOrder->shipped_at ? ' (auto-confirmed '.$sellerOrder->shipped_at->copy()->addDays((int) config('marketplace.orders.auto_deliver_days'))->format('M j').')' : '' }}. <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">Not here yet?</a>
                                    @elseif ($storeLate)
                                        {{ $store }} didn’t respond by {{ $returnCase->respond_by->format('M j, g:i A') }}. You can now ask JMMart to review your return.
                                    @elseif ($buyerLate)
                                        Your deadline to respond passed {{ $returnCase->deadline()->format('M j') }}. JMMart may close this return without a remedy; to still respond, contact support from the return page.
                                    @elseif ($storeMissed)
                                        {{ $store }} missed its {{ $returnCase->deadline()->format('M j') }} deadline. JMMart support can step in; details are on the return page.
                                    @elseif ($returnCase?->status === 'appealed')
                                        JMMart support is reviewing your return. Updates show on the return page.
                                    @elseif ($returnCase?->status === 'awaiting_receipt' && $returnCase->return_by)
                                        You sent the item back. {{ $store }} has until {{ $returnCase->return_by->format('M j') }} to confirm it arrived.
                                    @elseif ($returnCase?->status === 'awaiting_fulfillment' && $returnCase->fulfill_by)
                                        {{ $store }} has until {{ $returnCase->fulfill_by->format('M j') }} to send your {{ $returnCase->remedy ?? 'remedy' }}.
                                    @elseif ($returnCase?->status === 'awaiting_return')
                                        @if ($returnCase->return_by?->isPast())
                                            Your {{ $returnCase->return_by->format('M j') }} send-back date passed. Record the handoff now; until you do, JMMart may close this return without a remedy.
                                        @else
                                            Send the item back to {{ $store }}@if ($returnCase->return_by) by {{ $returnCase->return_by->format('M j') }}@endif, then record the handoff on the return page.
                                        @endif
                                    @elseif ($returnCase?->status === 'awaiting_confirmation')
                                        {{ $store }} recorded your {{ $returnCase->remedy ?? 'remedy' }}. {{ $returnCase->buyer_respond_by === null ? 'Confirm you got it, or dispute it, on the return page.' : ($returnCase->buyer_respond_by->isFuture() ? 'Confirm you got it, or dispute it, by '.$returnCase->buyer_respond_by->format('M j, g:i A').'.' : 'Your confirmation window has passed, so JMMart may close this as complete. Dispute it on the return page if it didn’t arrive.') }}
                                    @elseif ($returnDecision)
                                        {{ $store }} answered your return. {{ $returnCase->buyer_respond_by ? 'Decide by '.$returnCase->buyer_respond_by->format('M j, g:i A').' on the return page.' : 'Decide on the return page.' }}
                                    @elseif ($returnFinished)
                                        Your return is {{ $returnCase->status === 'completed' ? 'complete' : ($returnCase->status === 'denied' ? 'closed: the claim was denied' : 'closed') }}. Details are on the return page.
                                    @elseif ($returnCase?->status === 'awaiting_seller' && $returnCase->respond_by)
                                        Your return request is with {{ $store }}, which has until {{ $returnCase->respond_by->format('M j, g:i A') }} to respond.
                                    @elseif ($returnOpen)
                                        Your return request is with {{ $store }}. Follow it on the return page.
                                    @elseif ($canReturn)
                                        {{ $returnSoon ? 'Returns close '.($returnEndsAt->isToday() ? 'today' : 'tomorrow').' at '.$returnEndsAt->format('g:i A').'.' : 'Returns are open until '.$returnEndsAt->format('M j').'.' }} <a href="{{ route('orders.show', $order) }}#returns" class="{{ $inlineLink }}">Request return</a>
                                    @elseif ($returnClosedRecently)
                                        The {{ $returnDays }}-day return window has closed. <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">Contact support</a> if something’s wrong.
                                    @endif
                                </p>
                            @endunless

                            <footer class="mt-auto border-t border-line-soft pt-3">
                                <div class="flex items-end justify-between gap-3">
                                    <div class="min-w-0 text-[11px] leading-4 text-muted">
                                        <p>
                                            {{ \App\Models\Payment::METHOD_LABELS[$method] ?? 'Payment' }}@if ($paymentState && ! $needsPayment) <span aria-hidden="true">·</span> <span @class(['font-semibold', 'text-warning-text' => in_array($paymentState, ['To pay', 'Refund due'], true), 'text-success' => in_array($paymentState, ['Paid', 'Refunded'], true), 'text-ink' => in_array($paymentState, ['Reference sent', 'Pay on delivery'], true)])>{{ $paymentState }}</span>@endif
                                        </p>
                                        @if ($order->seller_orders_count > 1)
                                            <p class="mt-1">Checkout total ₱{{ number_format($order->total, 2) }} across {{ $order->seller_orders_count }} stores</p>
                                        @endif
                                    </div>
                                    <p class="shrink-0 text-right text-[11px] leading-4 text-muted">Store total<strong class="mt-0.5 block text-lg font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format($sellerOrder->total, 2) }}</strong></p>
                                </div>

                                <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}" class="-ml-1.5">@csrf<button type="submit" class="{{ $quietAction }}">Message seller</button></form>
                                    <a href="{{ route('orders.show', $order) }}" class="{{ $quietAction }}">View order</a>
                                    @if ($viewReturnHref)
                                        <a href="{{ $viewReturnHref }}" class="{{ $quietAction }}">View return</a>
                                    @endif
                                    @if ($alsoReview)
                                        <a href="{{ route('orders.show', $order) }}#review-item-{{ $reviewableItemId }}" class="{{ $quietAction }}">Write review</a>
                                    @endif
                                    @if ($nextAction && $nextAction[1] !== null)
                                        <a href="{{ $nextAction[1] }}" class="{{ $primaryAction }} ml-auto">{{ $nextAction[0] }}</a>
                                    @endif
                                </div>

                                @if ($nextAction && $nextAction[1] === null)
                                    {{--
                                        Confirming starts the return window and closes delivery, so the first press asks and the
                                        second posts. Without Alpine the button is a plain submit, so it still works.
                                    --}}
                                    <form method="POST" action="{{ route('orders.received', $sellerOrder) }}" x-data="{ confirming: false, sending: false }" @submit="sending = true" @pageshow.window="sending = false; confirming = false" @keydown.escape="if (confirming) { confirming = false; $nextTick(() => $refs.received.focus()) }" class="mt-2 flex flex-wrap items-center justify-end gap-1.5" role="group" aria-label="Confirm {{ $store }} order received">
                                        @csrf
                                        <span x-cloak x-show="confirming" id="received-note-{{ $sellerOrder->id }}" class="text-meta text-ink max-sm:w-full">Got the parcel? You’ll have {{ $returnDays }} days to request a return.</span>
                                        <button type="button" x-cloak x-show="confirming" @click="confirming = false; $nextTick(() => $refs.received.focus())" class="{{ $outlineAction }} max-sm:flex-1">Not yet</button>
                                        <button type="submit" x-ref="received" @click="if (! confirming) { $event.preventDefault(); confirming = true }" :disabled="sending" :aria-describedby="confirming ? 'received-note-{{ $sellerOrder->id }}' : null" class="{{ $primaryAction }} flex-1 disabled:cursor-wait disabled:opacity-70 sm:flex-none" x-text="sending ? 'Saving…' : (confirming ? 'Yes, received' : 'Order received')">Order received</button>
                                    </form>
                                @endif
                            </footer>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-line bg-surface px-5 py-10 text-center md:col-span-2 xl:col-span-3">
                        @if ($hasFilters)
                            <h3 class="text-lg font-semibold text-ink">{{ $search !== '' ? 'No purchases match “'.$search.'”'.($tab !== 'all' ? ' in '.$tabs[$tab] : '') : 'No purchases match those filters' }}</h3>
                            <p class="mx-auto mt-1.5 max-w-sm text-meta text-muted">Try an order number, a store name, or part of a product name.</p>
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                @if ($search !== '')<a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="{{ $outlineAction }}">Clear search</a>@endif
                                @if ($tab !== 'all' || filled($filters['status'] ?? null))<a href="{{ route('orders.index') }}" class="{{ $outlineAction }}">Reset filters</a>@endif
                            </div>
                        @else
                            <h3 class="text-lg font-semibold text-ink">{{ $emptyCopy[$tab][0] }}</h3>
                            <p class="mx-auto mt-1.5 max-w-sm text-meta text-muted">{{ $emptyCopy[$tab][1] }}</p>
                            @php($showAll = $tab !== 'all' && $tabCounts['all'] > 0)
                            @if ($tab === 'returns' && $tabCounts['delivered'] > 0)
                                <a href="{{ route('orders.index', ['tab' => 'delivered']) }}" class="{{ $outlineAction }} mt-4">View delivered orders</a>
                            @else
                                <a href="{{ $showAll ? route('orders.index') : route('catalog.index') }}" class="{{ $showAll ? $outlineAction : $primaryAction }} mt-4">{{ $showAll ? 'View all purchases' : 'Browse products' }}</a>
                            @endif
                        @endif
                    </div>
                @endforelse
            </section>

            @if ($sellerOrders->hasPages())<div class="mt-5">{{ $sellerOrders->links() }}</div>@endif
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
