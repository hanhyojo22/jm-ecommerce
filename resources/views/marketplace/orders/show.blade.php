<x-app-layout :error-summary="false">
    @php
        $statusStyles = ['pending'=>'bg-amber-50 text-warning-text ring-amber-200','confirmed'=>'bg-blue-50 text-brand-hover ring-blue-200','processing'=>'bg-blue-50 text-brand-hover ring-blue-200','shipped'=>'bg-blue-50 text-brand-hover ring-blue-200','delivered'=>'bg-green-50 text-success ring-green-200','cancelled'=>'bg-red-50 text-danger ring-red-200'];
        $paymentStyles = ['pending'=>'bg-amber-50 text-warning-text ring-amber-200','paid'=>'bg-green-50 text-success ring-green-200','failed'=>'bg-red-50 text-danger ring-red-200','refunded'=>'bg-slate-100 text-muted ring-line'];
        $currentStep = ['pending'=>1,'confirmed'=>2,'processing'=>2,'shipped'=>3,'delivered'=>4][$order->status] ?? 1;

        /*
         * Each store fulfils its own part on its own timing, so a single bar cannot say where the
         * order is once there is more than one. The bar moves to each store, and the top of the
         * page counts them off instead of picking one store's status to stand for all of them.
         */
        $storeStep = fn (string $status): int => ['pending'=>1,'confirmed'=>2,'processing'=>2,'shipped'=>3,'delivered'=>4][$status] ?? 1;
        // The badge sits beside the store it belongs to, so naming the seller again only makes it long.
        $statusLabel = fn (string $status): string => $status === 'pending' ? 'Awaiting confirmation' : ucfirst($status);
        $isOnlinePayment = $order->payment?->isOnline() ?? false;
        $awaitingOnlinePayment = $isOnlinePayment && $order->payment->status === 'pending'
            && $order->sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status === 'pending' && $sellerOrder->cod_collected_at === null);
        $methodLabel = \App\Models\Payment::METHOD_LABELS[$order->payment?->method] ?? 'Cash on delivery';
        $storeCount = $order->sellerOrders->count();
        $isMultiStore = $storeCount > 1;
        $deliveredStores = $order->sellerOrders->where('status', 'delivered')->count();
        $cancelledStores = $order->sellerOrders->where('status', 'cancelled')->count();
        $allSettled = $storeCount > 0 && $deliveredStores + $cancelledStores === $storeCount;
        $anyStoreMoved = $order->sellerOrders->contains(fn ($sellerOrder): bool => $sellerOrder->status !== 'pending');
        $multiStoreHeadline = $allSettled && $deliveredStores > 0 ? 'Delivered' : ($anyStoreMoved ? 'Order in progress' : 'Order received');

        /*
         * Every store already shows its own badge below, so the card counts them instead of
         * listing the same badges a second time.
         */
        $storeStatusCounts = $order->sellerOrders->countBy('status');
        $awaitingStores = $storeStatusCounts['pending'] ?? 0;
        $movingStores = ($storeStatusCounts['confirmed'] ?? 0) + ($storeStatusCounts['processing'] ?? 0) + ($storeStatusCounts['shipped'] ?? 0);
        $storeSummary = collect([
            $deliveredStores.' of '.$storeCount.' '.Str::plural('store', $storeCount).' delivered',
            $movingStores > 0 ? $movingStores.' on the way' : null,
            $awaitingStores > 0 ? $awaitingStores.' awaiting confirmation' : null,
            $cancelledStores > 0 ? $cancelledStores.' cancelled' : null,
        ])->filter()->join(' · ');

        if ($isMultiStore) {
            $progressNote = $allSettled && $deliveredStores > 0
                ? 'Every store has finished with your order. Share your experience by reviewing your items.'
                : 'Each store delivers its own items, so they arrive on their own timing.';
        } else {
            $progressNote = $order->status === 'delivered'
                ? 'Your order has arrived. Share your experience by reviewing your items.'
                : 'We will update this page as your order moves toward delivery.';
        }
    @endphp
    <div class="page-shell min-h-[calc(100dvh-4.5rem)]">
    {{-- The drill-down of the purchases list keeps that list's measure; the width lives in app.css. --}}
    <div class="page-container account-measure-orders">
        <a href="{{ route('orders.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover"><span aria-hidden="true">←</span> My purchases</a>
        @if($errors->any())<div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-rose-800" role="alert"><p class="font-semibold">We could not save your request.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(session('status'))<div class="mb-6 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm font-semibold text-brand-hover" role="status">{{ session('status') }}</div>@endif
        <div class="mt-7 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0"><h1 class="text-3xl font-semibold tracking-[-0.04em] text-ink sm:text-4xl">Order details</h1><div x-data="{ copied:false, copy() { navigator.clipboard?.writeText('{{ $order->number }}'); this.copied=true; setTimeout(() => this.copied=false, 1800) } }" class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted"><p class="break-words">Order #{{ $order->number }} · Placed {{ $order->created_at->format('M j, Y · g:i A') }}</p><button type="button" @click="copy" class="inline-flex min-h-11 items-center text-xs font-semibold text-brand hover:text-brand-hover" :aria-label="copied ? 'Order number copied' : 'Copy order number'" x-text="copied ? 'Copied' : 'Copy number'"></button></div></div>
            {{-- With several stores the order has no single status, so the badge says how far it is as a whole. --}}
            <span class="status-badge {{ $statusStyles[$order->status] }}">{{ $isMultiStore ? $multiStoreHeadline : ($order->status === 'pending' ? 'Awaiting seller confirmation' : ucfirst($order->status)) }}</span>
        </div>
        <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_350px]">
            <div class="space-y-6">
                @if($awaitingOnlinePayment)
                    @php($payBy = $order->created_at->copy()->addHours((int) config('marketplace.online_payment.payment_hours')))
                    <section id="pay-now" class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-[var(--color-warning-line)] bg-[var(--color-warning-wash)] p-5 sm:p-6" aria-labelledby="pay-now-heading">
                        <div class="min-w-0 max-w-[52ch]">
                            <h2 id="pay-now-heading" class="text-lg font-semibold text-ink">Pay ₱{{ number_format(bcadd((string) $order->total, (string) $order->payment->fee_amount, 2), 2) }} to confirm your order</h2>
                            <p class="mt-1 text-sm leading-6 text-warning-text">Pay with {{ $methodLabel }} on PayMongo before {{ $payBy->format('M j, g:i A') }}. Unpaid orders are cancelled and the items released.</p>
                        </div>
                        <a href="{{ route('orders.pay', $order) }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-brand px-5 text-sm font-semibold text-white transition hover:bg-brand-hover">Pay now</a>
                    </section>
                @endif
                <section class="rounded-lg border border-line bg-surface p-5 shadow-sm sm:p-6">
                    @if($order->status === 'cancelled')
                        <div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-lg bg-red-50 text-danger" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></span><div><h2 class="text-lg font-semibold text-ink">Order cancelled</h2><p class="mt-1 text-sm leading-6 text-muted">This order was cancelled, so delivery and review actions are unavailable.</p></div></div>
                    @else
                        <div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-lg {{ $order->status === 'delivered' ? 'bg-green-50 text-success' : 'bg-blue-50 text-brand' }}" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2">@if($order->status === 'delivered')<path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>@else<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/>@endif</svg></span><div><h2 class="text-lg font-semibold text-ink">{{ $isMultiStore ? $multiStoreHeadline : ($order->status === 'pending' ? 'Order received' : ucfirst($order->status)) }}</h2><p class="mt-1 text-sm leading-6 text-muted">{{ $progressNote }}</p></div></div>
                        @if($isMultiStore)
                            <p class="mt-4 text-sm font-semibold text-ink">{{ $storeSummary }}</p>
                        @else
                            <ol class="mt-7 grid grid-cols-4 gap-2" aria-label="Order progress">
                                @foreach(['Placed', 'Confirmed', 'Shipped', 'Delivered'] as $step => $label)
                                    <li class="min-w-0"><div class="h-1.5 rounded-full {{ $step + 1 <= $currentStep ? 'bg-brand' : 'bg-slate-200' }}"></div><p class="mt-3 text-xs font-semibold {{ $step + 1 <= $currentStep ? 'text-brand-hover' : 'text-muted' }}">{{ $label }}</p>@if($step === 0)<p class="mt-1 text-xs leading-4 text-muted">{{ $order->created_at->format('M j, g:i A') }}</p>@endif</li>
                                @endforeach
                            </ol>
                        @endif
                    @endif
                </section>
                @include("marketplace.orders._store-section")
                <section class="rounded-lg border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="delivery-payment-heading">
                    <h2 id="delivery-payment-heading" class="text-lg font-semibold text-ink">Delivery &amp; payment</h2>
                    <div class="mt-5 grid gap-6 sm:grid-cols-2">
                        <div><h3 class="text-xs font-semibold uppercase tracking-[0.1em] text-muted">Delivery address</h3><address class="mt-3 not-italic text-sm leading-6 text-muted">@if($order->recipient_name)<p class="font-semibold text-ink">{{ $order->recipient_name }}</p>@endif @if($order->address_line)<p>{{ $order->address_line }}</p>@endif @if($order->city || $order->province || $order->postal_code)<p>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}</p>@endif @if($order->phone)<p class="mt-2">{{ $order->phone }}</p>@endif @if($order->delivery_note)<p class="mt-2 whitespace-pre-line"><span class="font-semibold text-ink">Delivery note:</span> {{ $order->delivery_note }}</p>@endif</address></div>
                        <div><h3 class="text-xs font-semibold uppercase tracking-[0.1em] text-muted">Payment method</h3><p class="mt-3 text-sm font-semibold text-ink">{{ $methodLabel }}</p><p class="mt-1 text-sm leading-6 text-muted">{{ $isOnlinePayment ? ($order->payment->status === 'paid' ? 'Paid' : 'Pay').' online through PayMongo; each store receives its share automatically. Shipping is free.' : 'Payment is due when the order arrives. Shipping is free.' }}</p></div>
                    </div>
                </section>
                @include('marketplace.orders._returns')
                @include('marketplace.orders._issues')
            </div>
            <aside class="lg:sticky lg:top-24">
                <section class="rounded-lg border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="order-summary-heading">
                    <h2 id="order-summary-heading" class="text-lg font-semibold text-ink">Order summary</h2>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-muted">Items</dt><dd class="tabular-nums text-ink">₱{{ number_format($order->subtotal, 2) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-muted">Shipping</dt><dd class="font-semibold tabular-nums text-success">{{ $order->shipping_amount == 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd></div>
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3 border-t border-line pt-5"><dt class="font-semibold text-ink">Total</dt><dd class="whitespace-nowrap text-xl font-semibold tracking-[-0.04em] tabular-nums text-ink">₱{{ number_format($order->total, 2) }}</dd></div>
                    </dl>
                    <div class="mt-5 border-t border-line pt-5">
                        <p class="text-sm font-semibold text-ink">Payment</p>
                        @if($order->payment)
                            <div class="mt-2 flex items-center justify-between gap-3"><span class="text-sm text-muted">{{ $methodLabel }}</span><span class="status-badge {{ $paymentStyles[$order->payment->status] }}">{{ ucfirst($order->payment->status) }}</span></div>
                            <ul class="mt-3 space-y-1 text-xs text-muted">
                                @foreach ($order->sellerOrders as $sellerOrder)
                                    <li>{{ $sellerOrder->store->name }}: {{ match (true) {
                                        $sellerOrder->status === 'cancelled' => 'cancelled'.($sellerOrder->gateway_refund_id ? ', refunded' : ($isOnlinePayment && $sellerOrder->cod_collected_at ? ', refund pending' : '')),
                                        ! $isOnlinePayment => $sellerOrder->cod_collected_at ? 'COD collected' : 'COD collection not recorded',
                                        $sellerOrder->cod_collected_at !== null => 'paid',
                                        default => 'awaiting payment',
                                    } }}</li>
                                @endforeach
                            </ul>
                            @if (bccomp((string) $order->payment->fee_amount, '0', 2) > 0)<p class="mt-2 text-xs text-muted">Includes a ₱{{ number_format($order->payment->fee_amount, 2) }} PayMongo payment fee, paid on top of the total.</p>@endif
                        @else
                            <p class="mt-2 text-sm text-muted">Payment details unavailable.</p>
                        @endif
                    </div>
                    <div class="mt-5 border-t border-line pt-5 text-sm"><p class="font-semibold text-ink">Need help with this order?</p><a href="#order-help" class="inline-flex min-h-11 items-center font-semibold text-brand hover:text-brand-hover">Request help →</a><p class="text-xs leading-5 text-muted">Each seller handles their own items and delivery timing.</p></div>
                </section>
            </aside>
        </div>
        </div>
    </div>
</x-app-layout>
