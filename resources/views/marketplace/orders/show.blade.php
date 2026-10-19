<x-app-layout>
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
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-sm text-muted"><a href="{{ route('orders.index') }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:text-brand-hover">My purchases</a><span aria-hidden="true">/</span><span aria-current="page" class="font-semibold text-ink">Order details</span></nav>
        @if($errors->any())<div class="mb-6 rounded-[10px] border border-red-200 bg-red-50 p-4 text-sm text-rose-800" role="alert"><p class="font-semibold">We could not save your request.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(session('status'))<div class="mb-6 rounded-[10px] border border-blue-200 bg-blue-50 p-4 text-sm font-semibold text-brand-hover" role="status">{{ session('status') }}</div>@endif
        @if(session('order_placed'))
            <section class="mt-5 flex flex-wrap items-center gap-4 rounded-[18px] border border-green-200 bg-green-50 px-5 py-5 sm:px-7" aria-labelledby="order-confirmed-heading" data-od-id="confirmation" role="status">
                <span class="grid size-12 shrink-0 place-items-center rounded-full bg-green-100 text-success" aria-hidden="true"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg></span>
                <div class="min-w-0 flex-1"><h2 id="order-confirmed-heading" class="text-xl font-bold tracking-[-0.03em] text-ink sm:text-2xl">Order placed successfully</h2><p class="mt-1 text-sm leading-6 text-success">Thanks, {{ $order->recipient_name }}. The stores have your order and will confirm delivery timing.</p></div>
                <a href="{{ route('catalog.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-green-200 bg-white px-4 text-sm font-semibold text-success hover:bg-green-50">Continue shopping</a>
            </section>
        @endif
        <div class="mt-7 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-[0.14em] text-brand">My purchases / Order details</p><h1 class="mt-2 text-3xl font-bold tracking-[-0.04em] text-ink sm:text-4xl">Order details</h1><div x-data="{ copied:false, copy() { navigator.clipboard?.writeText('{{ $order->number }}'); this.copied=true; setTimeout(() => this.copied=false, 1800) } }" class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted"><p class="break-all">Order #{{ $order->number }} · Placed {{ $order->created_at->format('M j, Y · g:i A') }}</p><button type="button" @click="copy" class="inline-flex min-h-11 items-center text-xs font-semibold text-brand hover:text-brand-hover" :aria-label="copied ? 'Order number copied' : 'Copy order number'" x-text="copied ? 'Copied' : 'Copy number'"></button></div></div>
            {{-- With several stores the order has no single status, so the badge says how far it is as a whole. --}}
            <span class="status-badge {{ $statusStyles[$order->status] }}">{{ $isMultiStore ? $multiStoreHeadline : ($order->status === 'pending' ? 'Awaiting seller confirmation' : ucfirst($order->status)) }}</span>
        </div>
        <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_350px]">
            <div class="space-y-6">
                <section class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-6">
                    @if($order->status === 'cancelled')
                        <div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-red-50 text-danger" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></span><div><h2 class="text-lg font-semibold text-ink">Order cancelled</h2><p class="mt-1 text-sm leading-6 text-muted">This order was cancelled, so delivery and review actions are unavailable.</p></div></div>
                    @else
                        <div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $order->status === 'delivered' ? 'bg-green-50 text-success' : 'bg-blue-50 text-brand' }}" aria-hidden="true"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2">@if($order->status === 'delivered')<path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>@else<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/>@endif</svg></span><div><h2 class="text-lg font-semibold text-ink">{{ $isMultiStore ? $multiStoreHeadline : ($order->status === 'pending' ? 'Order received' : ucfirst($order->status)) }}</h2><p class="mt-1 text-sm leading-6 text-muted">{{ $progressNote }}</p></div></div>
                        @if($isMultiStore)
                            <p class="mt-4 text-sm font-semibold text-ink">{{ $storeSummary }}</p>
                        @else
                            <ol class="mt-7 grid grid-cols-4 gap-2" aria-label="Order progress">
                                @foreach(['Placed', 'Confirmed', 'Shipped', 'Delivered'] as $step => $label)
                                    <li class="min-w-0"><div class="h-1.5 rounded-full {{ $step + 1 <= $currentStep ? 'bg-brand' : 'bg-slate-200' }}"></div><p class="mt-3 text-xs font-semibold {{ $step + 1 <= $currentStep ? 'text-brand-hover' : 'text-muted' }}">{{ $label }}</p>@if($step === 0)<p class="mt-1 text-[11px] leading-4 text-muted">{{ $order->created_at->format('M j, g:i A') }}</p>@endif</li>
                                @endforeach
                            </ol>
                        @endif
                    @endif
                </section>
                @foreach($order->sellerOrders as $sellerOrder)<section class="overflow-hidden rounded-[14px] border border-line bg-surface"><header class="flex flex-wrap items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6"><div class="flex min-w-0 items-center gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-sm font-bold text-ink">{{ Str::upper(Str::substr($sellerOrder->store->name,0,1)) }}</span><div class="min-w-0"><h2 class="truncate font-semibold text-ink">{{ $sellerOrder->store->name }}</h2><p class="mt-0.5 text-sm text-muted">{{ $sellerOrder->items->count() }} {{ Str::plural('item',$sellerOrder->items->count()) }}</p></div></div><div class="flex flex-wrap items-center gap-3"><span class="status-badge {{ $statusStyles[$sellerOrder->status] }}">{{ $statusLabel($sellerOrder->status) }}</span><form method="POST" action="{{ route('messages.seller-orders.start',$sellerOrder) }}">@csrf<button class="min-h-10 rounded-lg border border-brand px-3 text-sm font-semibold text-brand-hover transition hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Message seller</button></form></div></header>@if($isMultiStore && $sellerOrder->status !== 'cancelled')<ol class="grid grid-cols-4 gap-2 border-b border-line px-5 py-4 sm:px-6" aria-label="{{ $sellerOrder->store->name }} progress">@foreach(['Placed', 'Confirmed', 'Shipped', 'Delivered'] as $step => $label)<li class="min-w-0"><div class="h-1.5 rounded-full {{ $step + 1 <= $storeStep($sellerOrder->status) ? 'bg-brand' : 'bg-slate-200' }}"></div><p class="mt-2 text-xs font-semibold {{ $step + 1 <= $storeStep($sellerOrder->status) ? 'text-brand-hover' : 'text-muted' }}">{{ $label }}</p></li>@endforeach</ol>@endif<div class="divide-y divide-line">@foreach($sellerOrder->items as $item)<div id="review-item-{{ $item->id }}" class="flex gap-4 px-5 py-5 sm:px-6"><div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100">@if(($item->image_path || $item->product?->images->first()))<img src="{{ Storage::disk('product_images')->url(($item->image_path ?? $item->product->images->first()->path)) }}" alt="{{ $item->product_name }}" class="h-full w-full object-contain">@else<svg viewBox="0 0 24 24" class="size-7 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m4 18 5-5 3 3 3-3 5 5"/></svg><span class="sr-only">Product image unavailable</span>@endif</div><div class="min-w-0 flex-1"><h3 class="break-words font-semibold leading-6 text-ink">{{ $item->product_name }}</h3>@if($item->variant_label)<p class="mt-1 text-xs font-medium text-brand">{{ $item->variant_label }}</p>@endif<p class="mt-1 text-sm text-muted">Qty {{ $item->quantity }} · ₱{{ number_format($item->unit_price,2) }} each</p>@if($sellerOrder->status === 'delivered' && $item->product_id)<div x-data="{ open:false, rating:{{ $item->review?->rating ?? 5 }}, submitting:false, files:0, trigger:null, show(event) { this.trigger=event.currentTarget; this.open=true; this.$nextTick(() => this.$refs.modal.querySelector('[data-autofocus]').focus()) }, close() { this.open=false; this.$nextTick(() => this.trigger?.focus()) } }" class="mt-4">@if($item->review)<div class="flex flex-wrap items-center gap-x-3 gap-y-2"><span class="flex gap-0.5 text-amber-500" aria-label="{{ $item->review->rating }} out of 5 stars">@foreach(range(1,5) as $star)<svg viewBox="0 0 20 20" class="size-4 {{ $star <= $item->review->rating ? 'fill-current' : 'fill-slate-200' }}"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z"/></svg>@endforeach</span><span class="text-sm font-semibold text-success">Reviewed</span><a href="{{ route('catalog.show',[$item->product->store->slug,$item->product->slug]) }}#reviews" class="text-sm font-semibold text-brand hover:text-brand-hover">View review</a><button type="button" @click="show($event)" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:text-brand-hover">Edit review</button></div>@else<button type="button" @click="show($event)" class="inline-flex min-h-10 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Write a review</button>@endif<div x-show="open" x-cloak @keydown.escape.window="close" class="fixed inset-0 z-50 flex items-end bg-slate-950/50 sm:items-center sm:justify-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="review-heading-{{ $item->id }}"><div x-ref="modal" class="max-h-[92dvh] w-full overflow-y-auto rounded-t-[14px] bg-surface p-6 shadow-xl sm:max-w-xl sm:rounded-[14px]"><div class="flex items-start justify-between gap-5"><div><h3 id="review-heading-{{ $item->id }}" class="text-xl font-semibold tracking-[-0.02em] text-ink">{{ $item->review ? 'Edit your review' : 'Review '.$item->product_name }}</h3><p class="mt-1 text-sm leading-6 text-muted">Your review is linked to this delivered order and appears as a verified purchase.</p></div><button type="button" @click="close" class="grid size-11 shrink-0 place-items-center rounded-lg text-muted hover:bg-canvas" aria-label="Close review form"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button></div><form method="POST" action="{{ $item->review ? route('reviews.update',$item->review) : route('reviews.store',$item) }}" enctype="multipart/form-data" @submit="submitting=true">@csrf @if($item->review)@method('PATCH')@endif<fieldset class="mt-7"><legend class="text-sm font-semibold text-ink">Your rating</legend><div class="mt-3 flex gap-1.5" role="radiogroup" aria-label="Your rating">@foreach([1=>'Poor',2=>'Fair',3=>'Good',4=>'Very good',5=>'Excellent'] as $value=>$label)<label class="cursor-pointer"><input type="radio" name="rating" value="{{ $value }}" x-model="rating" @checked(($item->review?->rating ?? 5)===$value) class="sr-only"><svg viewBox="0 0 20 20" class="size-9 transition" :class="rating >= {{ $value }} ? 'fill-amber-400 text-amber-400' : 'fill-slate-200 text-slate-200'" aria-hidden="true"><path d="m10 1.6 2.5 5.1 5.6.8-4 3.9.9 5.5-5-2.7-5 2.7.9-5.5-4-3.9 5.6-.8L10 1.6Z"/></svg><span class="sr-only">{{ $label }}</span></label>@endforeach</div><p class="mt-2 text-sm font-medium text-muted" x-text="{1:'Poor',2:'Fair',3:'Good',4:'Very good',5:'Excellent'}[rating]"></p></fieldset><div class="mt-6 grid gap-4"><label><span class="field-label">Review title <span class="font-normal text-muted">(optional)</span></span><input data-autofocus name="title" maxlength="120" value="{{ $item->review?->title }}" class="field-control"></label><label><span class="field-label">Your review <span class="font-normal text-muted">(optional)</span></span><textarea name="body" maxlength="3000" rows="5" class="field-control">{{ $item->review?->body }}</textarea></label><label><span class="field-label">Add photos <span class="font-normal text-muted">(optional, up to 5)</span></span><input name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" @change="files=Math.min($event.target.files.length,5)" class="mt-1 block w-full text-sm text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-hover hover:file:bg-blue-50"><p x-show="files" x-cloak class="mt-2 text-sm text-muted" x-text="files + (files===1 ? ' photo selected' : ' photos selected')"></p></label></div><div x-show="submitting" class="mt-5" aria-live="polite"><p class="text-sm font-semibold text-brand-hover">Saving your review…</p><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-blue-50"><span class="block h-full w-2/3 animate-pulse rounded-full bg-brand"></span></div></div><div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button type="button" @click="close" class="min-h-11 rounded-lg px-4 text-sm font-semibold text-muted hover:bg-canvas">Cancel</button><button :disabled="submitting" class="min-h-11 rounded-lg bg-brand px-5 text-sm font-semibold text-white shadow-sm hover:bg-brand-hover disabled:cursor-wait disabled:bg-blue-300"><span x-text="submitting ? 'Saving…' : '{{ $item->review ? 'Save changes' : 'Publish review' }}'"></span></button></div></form>@if($item->review)<form method="POST" action="{{ route('reviews.destroy',$item->review) }}" class="mt-3 border-t border-line pt-4">@csrf @method('DELETE')<button class="inline-flex min-h-11 items-center text-sm font-semibold text-danger hover:text-danger">Delete review</button></form>@endif</div></div></div>@endif</div><p class="shrink-0 text-right text-sm font-semibold tabular-nums text-ink sm:text-base">₱{{ number_format($item->line_total,2) }}</p></div>@endforeach</div></section>@endforeach
                <section class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="delivery-payment-heading">
                    <h2 id="delivery-payment-heading" class="text-lg font-semibold text-ink">Delivery &amp; payment</h2>
                    <div class="mt-5 grid gap-6 sm:grid-cols-2">
                        <div><h3 class="text-xs font-bold uppercase tracking-[0.1em] text-muted">Delivery address</h3><address class="mt-3 not-italic text-sm leading-6 text-muted">@if($order->recipient_name)<p class="font-semibold text-ink">{{ $order->recipient_name }}</p>@endif @if($order->address_line)<p>{{ $order->address_line }}</p>@endif @if($order->city || $order->province || $order->postal_code)<p>{{ collect([$order->city, $order->province])->filter()->join(', ') }} {{ $order->postal_code }}</p>@endif @if($order->phone)<p class="mt-2">{{ $order->phone }}</p>@endif @if($order->delivery_note)<p class="mt-2 whitespace-pre-line"><span class="font-semibold text-ink">Delivery note:</span> {{ $order->delivery_note }}</p>@endif</address></div>
                        <div><h3 class="text-xs font-bold uppercase tracking-[0.1em] text-muted">Payment method</h3><p class="mt-3 text-sm font-semibold text-ink">Cash on delivery</p><p class="mt-1 text-sm leading-6 text-muted">Payment is due when the order arrives. Shipping is free.</p></div>
                    </div>
                </section>
                @include('marketplace.orders._returns')
                @include('marketplace.orders._issues')
            </div>
            <aside class="lg:sticky lg:top-24">
                <section class="rounded-[18px] border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="order-summary-heading">
                    <h2 id="order-summary-heading" class="text-lg font-semibold text-ink">Order summary</h2>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-muted">Items</dt><dd class="tabular-nums text-ink">₱{{ number_format($order->subtotal, 2) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-muted">Shipping</dt><dd class="font-semibold tabular-nums text-success">{{ $order->shipping_amount == 0 ? 'Free' : '₱'.number_format($order->shipping_amount, 2) }}</dd></div>
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3 border-t border-line pt-5"><dt class="font-semibold text-ink">Total</dt><dd class="whitespace-nowrap text-xl font-bold tracking-[-0.04em] tabular-nums text-ink">₱{{ number_format($order->total, 2) }}</dd></div>
                    </dl>
                    <div class="mt-5 border-t border-line pt-5">
                        <p class="text-sm font-semibold text-ink">Payment</p>
                        @if($order->payment)
                            <div class="mt-2 flex items-center justify-between gap-3"><span class="text-sm text-muted">{{ str($order->payment->method)->upper() }}</span><span class="status-badge {{ $paymentStyles[$order->payment->status] }}">{{ ucfirst($order->payment->status) }}</span></div>
                            <ul class="mt-3 space-y-1 text-xs text-muted">@foreach ($order->sellerOrders as $sellerOrder)<li>{{ $sellerOrder->store->name }}: {{ $sellerOrder->cod_collected_at ? 'COD collected' : 'COD collection not recorded' }}</li>@endforeach</ul>
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
