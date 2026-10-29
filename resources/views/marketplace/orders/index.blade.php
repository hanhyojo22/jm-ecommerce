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

            <div class="mt-4 flex flex-col gap-3 2xl:flex-row 2xl:items-end 2xl:justify-between 2xl:gap-6">
                {{-- The tabs read as the order's journey, bracketed by All and by what comes after delivery,
                     so a rule sits between those groups. Only a tab where the next move is the buyer's gets a
                     warm count; every other count stays quiet. The track scrolls on a phone, so the selected
                     tab and whichever tab takes focus are kept in view. --}}
                @php
                    $buyerTurn = ['to_pay' => $tabCounts['to_pay'] > 0, 'returns' => isset($attentionCounts['returns'])];
                @endphp
                <nav class="relative flex min-w-0 items-stretch overflow-x-auto border-b border-line [scrollbar-width:none] max-sm:[mask-image:linear-gradient(to_right,transparent,#000_20px,#000_calc(100%-20px),transparent)] [&::-webkit-scrollbar]:hidden 2xl:flex-1" aria-label="Purchase status filters" x-data @focusin="$event.target.scrollIntoView({ block: 'nearest', inline: 'center' })">
                    @foreach ($tabs as $key => $label)
                        @if (in_array($key, ['to_pay', 'to_review'], true))<span aria-hidden="true" class="mx-1.5 h-4 w-px shrink-0 self-center bg-line"></span>@endif
                        <a href="{{ route('orders.index', [...request()->except('tab', 'page'), 'tab' => $key]) }}" @class([
                            '-mb-px inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-2.5 text-meta font-semibold transition-colors focus-visible:outline-offset-[-2px] lg:min-h-10',
                            'border-brand text-ink' => $tab === $key,
                            'border-transparent text-muted hover:border-line hover:text-ink' => $tab !== $key,
                        ]) @if ($tab === $key) aria-current="page" x-init="$el.parentElement.scrollLeft = Math.max(0, $el.offsetLeft - ($el.parentElement.clientWidth - $el.offsetWidth) / 2)" @endif>
                            {{ $label }}
                            @if ($buyerTurn[$key] ?? false)
                                <span class="inline-grid h-5 min-w-5 place-items-center rounded-full border border-[var(--color-warning-line)] bg-[var(--color-warning-wash)] px-1.5 text-[11px] font-semibold tabular-nums text-warning-text">{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}, waiting on you</span></span>
                            @elseif ($tabCounts[$key] > 0 || $tab === $key)
                                <span @class(['text-[11px] font-semibold tabular-nums', 'text-brand' => $tab === $key, 'text-muted' => $tab !== $key])>{{ $tabCounts[$key] }}<span class="sr-only"> {{ Str::plural('order', $tabCounts[$key]) }}</span></span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ route('orders.index') }}" class="order-first flex min-w-0 items-start gap-2 sm:max-w-md 2xl:order-last 2xl:w-[288px] 2xl:shrink-0">
                    @if ($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                    @if (filled($filters['status'] ?? null))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <div class="min-w-0 flex-1">
                        <label for="purchase-search" class="sr-only">Search purchases</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                            <input id="purchase-search" name="search" value="{{ $search }}" type="search" maxlength="100" enterkeyhint="search" class="field-control pl-9 text-[16px] sm:text-meta lg:min-h-10 {{ $search !== '' ? 'pr-11' : '' }}" placeholder="Order #, store, item" @error('search') aria-invalid="true" aria-describedby="purchase-search-error" @enderror>
                            @if ($search !== '')
                                <a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="absolute right-0 top-0 grid size-11 place-items-center rounded-lg text-muted hover:text-ink lg:size-10" aria-label="Clear search"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></a>
                            @endif
                        </div>
                        <x-input-error id="purchase-search-error" :messages="$errors->get('search')" class="mt-2" />
                    </div>
                    <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-line bg-surface px-3.5 text-meta font-semibold text-ink transition-colors hover:border-brand hover:text-brand-hover lg:min-h-10">Search</button>
                </form>
            </div>
            @if (filled($filters['status'] ?? null) && isset($statusStyles[$filters['status']]))
                <p class="mt-2 text-meta text-muted">Filtered to checkouts marked {{ $statusStyles[$filters['status']][0] }} <span aria-hidden="true">·</span> <a href="{{ route('orders.index', request()->except('status', 'page')) }}" class="{{ $inlineLink }}">Show all</a></p>
            @endif
            <x-input-error :messages="$errors->get('tab')" class="mt-2" />
            <x-input-error :messages="$errors->get('status')" class="mt-2" />

            <section id="purchases" class="mt-6 scroll-mt-24" aria-labelledby="purchase-results-heading">
                <h2 id="purchase-results-heading" class="sr-only">{{ $tabs[$tab] }} purchases{{ $search !== '' ? ' matching “'.$search.'”' : '' }} ({{ $sellerOrders->total() }})</h2>

                @php
                    $orderGroups = $sellerOrders->getCollection()
                        ->groupBy(fn ($sellerOrder): string => $sellerOrder->order->created_at->toDateString())
                        ->sortKeysDesc();
                @endphp

                @forelse ($orderGroups as $groupOrders)
                    @php
                        $groupDate = $groupOrders->first()->order->created_at->copy()->startOfDay();
                        $groupLabel = $groupDate->isToday() ? 'Today' : ($groupDate->isYesterday() ? 'Yesterday' : $groupDate->format($groupDate->isCurrentYear() ? 'M j' : 'M j, Y'));
                        $groupMeta = $groupDate->isToday() || $groupDate->isYesterday()
                            ? $groupDate->format('M j')
                            : $groupOrders->count().' '.Str::plural('order', $groupOrders->count());
                    @endphp
                    <section @class(['grid gap-2 lg:grid-cols-[112px_minmax(0,1fr)] lg:gap-6', 'mt-7' => ! $loop->first]) aria-label="Orders from {{ $groupDate->format('M j, Y') }}">
                        <div class="flex items-baseline gap-2 lg:block lg:pt-2 lg:text-right">
                            <h3 class="text-xl font-semibold tracking-tight text-ink lg:text-[23px]">{{ $groupLabel }}</h3>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.06em] text-muted lg:mt-1">{{ $groupMeta }}</p>
                        </div>

                        <div class="relative border-l border-line pb-1 pl-5 pt-2 lg:pl-7">
                            @foreach ($groupOrders as $sellerOrder)
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
                                    $leadItemName = $items->first()?->product_name;
                                    $orderLabel = $leadItemName ? $leadItemName.' order' : 'order';
                                    $thumbPaths = $items
                                        ->map(fn ($item) => $item->image_path ?? $item->product?->thumbnail?->path)
                                        ->filter()
                                        ->take(3)
                                        ->values();
                                    $remainingThumbs = max(0, $items->count() - $thumbPaths->count());
                                    $shortNumber = Str::upper(Str::substr($order->number, -6));
                                    // The badge is the order's own state; buyer/store lateness replaces it with the turn that matters now.
                                    $overlayLabel = $statusLabel;
                                    $overlayDot = $statusDot;
                                    $overlayText = $statusText;
                                    if ($buyerLate) {
                                        [$overlayLabel, $overlayDot, $overlayText] = ['Response window passed', 'bg-muted', 'text-muted'];
                                    } elseif ($storeMissed) {
                                        [$overlayLabel, $overlayDot, $overlayText] = [$storeLate ? 'Store missed its deadline' : 'Store is running late', $storeLate ? 'bg-warning' : 'bg-muted', $storeLate ? 'text-warning-text' : 'text-ink'];
                                    }
                                    // The headline names the event, so the timeline reads as activity rather than a list of cards.
                                    $headline = match (true) {
                                        $refundDue => $store.' owes you a refund',
                                        $sellerOrder->status === 'cancelled' => 'Your '.$orderLabel.' was cancelled',
                                        $cancelRequest => 'Waiting for '.$store.' to answer your cancellation',
                                        $needsPayment => 'Pay for your '.$orderLabel,
                                        $storeLate => $store.' missed its return deadline',
                                        $buyerLate => 'Your return response window has passed',
                                        $storeMissed => $store.' missed a return deadline',
                                        $returnCase?->status === 'appealed' => 'JMMart is reviewing your return',
                                        $returnCase?->status === 'awaiting_receipt' => 'Your return is on its way to '.$store,
                                        $returnCase?->status === 'awaiting_fulfillment' => 'Waiting for '.$store.' to send your '.($returnCase->remedy ?? 'remedy'),
                                        $returnCase?->status === 'awaiting_return' => 'Send your '.($leadItemName ?? 'item').' back to '.$store,
                                        $returnCase?->status === 'awaiting_confirmation' => 'Confirm your '.($returnCase->remedy ?? 'remedy').' from '.$store,
                                        $returnDecision => $store.' answered your return',
                                        $returnFinished => 'Your return is '.($returnCase->status === 'completed' ? 'complete' : ($returnCase->status === 'denied' ? 'denied' : 'closed')),
                                        $returnCase?->status === 'awaiting_seller' => 'Waiting for '.$store.' to answer your return',
                                        $returnOpen => 'Your return with '.$store.' is in progress',
                                        $sellerOrder->status === 'shipped' => 'Your '.$orderLabel.' is on the way',
                                        in_array($sellerOrder->status, ['confirmed', 'processing'], true) => $store.' is preparing your '.$orderLabel,
                                        $sellerOrder->status === 'pending' => 'Waiting for '.$store.' to confirm your order',
                                        $sellerOrder->status === 'delivered' && $reviewableItemId => 'Your '.$orderLabel.' arrived',
                                        $sellerOrder->status === 'delivered' => 'Your '.$orderLabel.' was delivered',
                                        default => $store.' order update',
                                    };
                                    $isUrgent = $needsPayment || $returnDecision || $refundDue || $storeLate;
                                    $dotTone = match (true) {
                                        $isUrgent => 'bg-warning',
                                        $sellerOrder->status === 'delivered' && ! ($returnCase && ! $returnFinished) => 'bg-success',
                                        in_array($sellerOrder->status, ['shipped', 'confirmed', 'processing'], true) => 'bg-brand',
                                        default => 'bg-[var(--color-border-strong)]',
                                    };
                                    $noteIsUrgent = $needsPayment || $returnSoon || $refundDue || $returnDecision;
                                @endphp

                                <article class="relative border-b border-line-soft py-4 last:border-0 lg:py-5" aria-labelledby="purchase-{{ $sellerOrder->id }} purchase-number-{{ $sellerOrder->id }}">
                                    <span @class(['absolute -left-[26px] top-[23px] size-2.5 rounded-full ring-4 ring-canvas lg:-left-[34px] lg:top-[27px]', $dotTone]) aria-hidden="true"></span>

                                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px] lg:gap-5">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] leading-4 text-muted">
                                                <span @class(['inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-2 py-0.5 text-[11px] font-bold', $overlayText])>
                                                    <span class="size-1.5 rounded-full {{ $overlayDot }}" aria-hidden="true"></span>
                                                    {{ $overlayLabel }}
                                                </span>
                                                <span class="font-semibold text-ink"><a href="{{ route('catalog.store', $sellerOrder->store->slug) }}" class="hover:text-brand-hover">{{ $store }}</a></span>
                                                <a id="purchase-number-{{ $sellerOrder->id }}" href="{{ route('orders.show', $order) }}" class="inline-flex min-h-6 items-center font-medium tabular-nums text-ink underline-offset-2 hover:underline"><span aria-hidden="true">#…{{ $shortNumber }}</span><span class="sr-only">Order {{ $order->number }}</span></a>
                                            </div>

                                            @if (! $buyerLate && ! $storeMissed && $returnCase && ($tab === 'returns' || ! $returnFinished))
                                                <x-returns.status-badge :status="$returnCase->status" buyer class="mt-2" />
                                            @elseif ($tab === 'returns' && $returnIssue)
                                                <p class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold text-warning-text"><span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Return request open</p>
                                            @endif

                                            <h4 id="purchase-{{ $sellerOrder->id }}" class="mt-2 text-[15px] font-semibold leading-snug tracking-[-0.01em] text-ink">{{ $headline }}</h4>

                                            @unless ($needsPayment && $payBy?->isPast())
                                                <p @class(['mt-1 text-meta leading-5', 'text-warning-text' => $noteIsUrgent, 'text-muted' => ! $noteIsUrgent])>
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
                                                        You can still change your mind. <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">Ask to cancel</a>
                                                    @elseif (in_array($sellerOrder->status, ['confirmed', 'processing'], true))
                                                        {{ $sellerOrder->status === 'confirmed' ? 'It will be handed to the courier when ready.' : 'It’s almost ready to ship.' }}@if ($sellerOrder->status === 'confirmed') <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">Ask to cancel</a>@endif
                                                    @elseif ($sellerOrder->status === 'shipped')
                                                        Confirm with Order received once it arrives{{ $sellerOrder->shipped_at ? ' (auto-confirmed '.$sellerOrder->shipped_at->copy()->addDays((int) config('marketplace.orders.auto_deliver_days'))->format('M j').')' : '' }}. <a href="{{ route('orders.show', $order) }}#order-help" class="{{ $inlineLink }}">Not here yet?</a>
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

                                            <div class="mt-3 flex items-center gap-2.5">
                                                <div class="flex shrink-0">
                                                    @forelse ($thumbPaths as $thumbPath)
                                                        <img src="{{ Storage::disk('product_images')->url($thumbPath) }}" alt="" width="36" height="36" loading="lazy" class="-ml-2 size-9 rounded-lg border-2 border-surface object-cover first:ml-0">
                                                    @empty
                                                        <span class="grid size-9 place-items-center rounded-lg bg-paper text-muted">
                                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 8h16l-1 12H5L4 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                                                            <span class="sr-only">Product image unavailable</span>
                                                        </span>
                                                    @endforelse
                                                    @if ($remainingThumbs > 0)
                                                        <span class="-ml-2 grid size-9 place-items-center rounded-lg border-2 border-surface bg-paper text-[11px] font-bold text-muted">+{{ $remainingThumbs }}</span>
                                                    @endif
                                                </div>
                                                <p class="min-w-0 text-[11px] leading-4 text-muted">{{ $leadItemName ?? 'Order items' }}@if ($items->count() > 1) <span aria-hidden="true">·</span> {{ $items->count() }} items @endif</p>
                                            </div>

                                            <p class="mt-2 text-[11px] leading-4 text-muted">
                                                {{ \App\Models\Payment::METHOD_LABELS[$method] ?? 'Payment' }}@if ($paymentState && ! $needsPayment) <span aria-hidden="true">·</span> <span @class(['font-semibold', 'text-warning-text' => in_array($paymentState, ['To pay', 'Refund due'], true), 'text-success' => in_array($paymentState, ['Paid', 'Refunded'], true), 'text-ink' => in_array($paymentState, ['Reference sent', 'Pay on delivery'], true)])>{{ $paymentState }}</span>@endif
                                                @if ($order->seller_orders_count > 1)<span aria-hidden="true">·</span> Checkout total ₱{{ number_format($order->total, 2) }} across {{ $order->seller_orders_count }} stores @endif
                                            </p>

                                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                                <form method="POST" action="{{ route('messages.seller-orders.start', $sellerOrder) }}" class="-ml-1.5">@csrf<button type="submit" class="{{ $quietAction }}">Message seller</button></form>
                                                <a href="{{ route('orders.show', $order) }}" class="{{ $quietAction }}">View order</a>
                                                @if ($viewReturnHref)
                                                    <a href="{{ $viewReturnHref }}" class="{{ $quietAction }}">View return</a>
                                                @endif
                                                @if ($alsoReview)
                                                    <a href="{{ route('orders.show', $order) }}#review-item-{{ $reviewableItemId }}" class="{{ $quietAction }}">Write review</a>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t border-line-soft pt-3 lg:mt-0 lg:flex-col lg:items-end lg:justify-start lg:gap-3 lg:border-0 lg:pt-0">
                                            <div class="text-left lg:text-right">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.05em] text-muted">Store total</p>
                                                <p class="mt-1 text-lg font-semibold tabular-nums tracking-tight text-ink">₱{{ number_format($sellerOrder->total, 2) }}</p>
                                            </div>

                                            <div class="flex flex-col items-end">
                                                @if ($nextAction && $nextAction[1] !== null)
                                                    <a href="{{ $nextAction[1] }}" class="{{ $primaryAction }} whitespace-nowrap">{{ $nextAction[0] }}</a>
                                                @elseif ($nextAction && $nextAction[1] === null)
                                                    {{--
                                                        Confirming starts the return window and closes delivery, so the first press asks and the
                                                        second posts. Without Alpine the button is a plain submit, so it still works.
                                                    --}}
                                                    <form method="POST" action="{{ route('orders.received', $sellerOrder) }}" x-data="{ confirming: false, sending: false }" @submit="sending = true" @pageshow.window="sending = false; confirming = false" @keydown.escape="if (confirming) { confirming = false; $nextTick(() => $refs.received.focus()) }" class="flex flex-wrap items-center justify-end gap-1.5" role="group" aria-label="Confirm {{ $store }} order received">
                                                        @csrf
                                                        <span x-cloak x-show="confirming" id="received-note-{{ $sellerOrder->id }}" class="w-full text-left text-[11px] leading-4 text-ink">Got the parcel? You’ll have {{ $returnDays }} days to request a return.</span>
                                                        <button type="button" x-cloak x-show="confirming" @click="confirming = false; $nextTick(() => $refs.received.focus())" class="{{ $outlineAction }} max-sm:flex-1">Not yet</button>
                                                        <button type="submit" x-ref="received" @click="if (! confirming) { $event.preventDefault(); confirming = true }" :disabled="sending" :aria-describedby="confirming ? 'received-note-{{ $sellerOrder->id }}' : null" class="{{ $primaryAction }} flex-1 disabled:cursor-wait disabled:opacity-70 sm:flex-none" x-text="sending ? 'Saving…' : (confirming ? 'Yes, received' : 'Order received')">Order received</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="rounded-2xl border border-dashed border-line bg-surface px-5 py-12 text-center">
                        @if ($hasFilters)
                            <h3 class="text-lg font-semibold text-ink">{{ $search !== '' ? 'No purchases match “'.$search.'”'.($tab !== 'all' ? ' in '.$tabs[$tab] : '') : 'No purchases match those filters' }}</h3>
                            <p class="mx-auto mt-2 max-w-sm text-meta text-muted">Try an order number, a store name, or part of a product name.</p>
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                @if ($search !== '')<a href="{{ route('orders.index', request()->except('search', 'page')) }}" class="{{ $outlineAction }}">Clear search</a>@endif
                                @if ($tab !== 'all' || filled($filters['status'] ?? null))<a href="{{ route('orders.index') }}" class="{{ $outlineAction }}">Reset filters</a>@endif
                            </div>
                        @else
                            <h3 class="text-lg font-semibold text-ink">{{ $emptyCopy[$tab][0] }}</h3>
                            <p class="mx-auto mt-2 max-w-sm text-meta text-muted">{{ $emptyCopy[$tab][1] }}</p>
                            @php($showAll = $tab !== 'all' && $tabCounts['all'] > 0)
                            @if ($tab === 'returns' && $tabCounts['delivered'] > 0)
                                <a href="{{ route('orders.index', ['tab' => 'delivered']) }}" class="{{ $outlineAction }} mt-4">View delivered orders</a>
                            @else
                                <a href="{{ $showAll ? route('orders.index') : route('catalog.index') }}" class="{{ $showAll ? $outlineAction : $primaryAction }} mt-4">{{ $showAll ? 'View all purchases' : 'Browse products' }}</a>
                            @endif
                        @endif
                    </div>
                @endforelse

                {{-- Load more swaps in the whole longer list rather than appending rows, because orders are
                     grouped by day and a day can straddle two batches. The link still works without script. --}}
                @if ($sellerOrders->total() > $sellerOrders->count() || $sellerOrders->count() > 10)
                    <div class="mt-8 flex flex-col items-center gap-3" x-data="{ loading: false, async more(link) {
                        if (this.loading) return;
                        this.loading = true;
                        const shown = document.querySelectorAll('#purchases article').length;
                        try {
                            const response = await fetch(link.href, { headers: { Accept: 'text/html' } });
                            if (! response.ok) throw new Error(response.status);
                            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('purchases');
                            document.getElementById('purchases').replaceWith(fresh);
                            history.replaceState(history.state, '', link.href);
                            const first = fresh.querySelectorAll('article')[shown];
                            first?.setAttribute('tabindex', '-1');
                            first?.focus({ preventScroll: true });
                        } catch {
                            window.location.assign(link.href);
                        }
                    } }">
                        <p class="text-meta text-muted">Showing {{ $sellerOrders->count() }} of {{ $sellerOrders->total() }} {{ Str::plural('order', $sellerOrders->total()) }}</p>
                        @if ($loadMoreUrl)
                            <a href="{{ $loadMoreUrl }}" @click.prevent="more($el)" :aria-busy="loading" :class="loading && 'pointer-events-none opacity-60'" class="{{ $outlineAction }} min-w-[200px] justify-center">
                                <span x-show="! loading">Load more</span>
                                <span x-cloak x-show="loading">Loading…</span>
                            </a>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    </x-marketplace.account-shell>
</x-app-layout>
