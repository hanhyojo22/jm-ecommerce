<section id="order-help" class="rounded-[18px] border border-line bg-surface p-5 sm:p-6" aria-labelledby="order-help-heading" x-data="{ sellerOrder: @js(old('seller_order_id', request()->query('seller_order_id', $order->sellerOrders->first(fn ($sellerOrder) => ! $sellerOrder->issues->contains('status', 'open'))?->id))) }">
    <h2 id="order-help-heading" class="text-lg font-semibold text-ink">Need help with this order?</h2>
    <p class="mt-1 text-sm text-muted">Send a request to JMMart support. We review each request and reply here.</p>
    @foreach($order->sellerOrders as $sellerOrder)
        @foreach($sellerOrder->issues as $issue)
            <div class="mt-4 rounded-lg border border-line bg-canvas p-4 text-sm">
                <p class="font-semibold text-ink">{{ $sellerOrder->store->name }} · {{ str($issue->type)->replace('_', ' ')->title() }} · {{ ucfirst($issue->status) }}</p>
                <p class="mt-1 whitespace-pre-wrap text-muted">{{ $issue->details }}</p>
                @if($issue->resolution_note)<p class="mt-2 font-medium text-ink">Response: {{ $issue->resolution_note }}</p>@endif
            </div>
        @endforeach
    @endforeach
    @if($order->sellerOrders->contains(fn ($sellerOrder) => ! $sellerOrder->issues->contains('status', 'open')))
        <form method="POST" :action="@js(route('order-issues.store', '__seller_order__')).replace('__seller_order__', sellerOrder)" class="mt-5 grid gap-4">
            @csrf
            <label class="block"><span class="field-label">Store order</span><select x-model="sellerOrder" name="seller_order_id" class="field-control" required>@foreach($order->sellerOrders as $sellerOrder)<option value="{{ $sellerOrder->id }}" @disabled($sellerOrder->issues->contains('status', 'open'))>{{ $sellerOrder->store->name }} ({{ ucfirst($sellerOrder->status) }})</option>@endforeach</select></label>
            <label class="block"><span class="field-label">Request type</span><select name="type" class="field-control" required><option value="cancellation">Cancel order (before preparation)</option><option value="delivery">Delivery help</option><option value="other">Other order help</option></select></label>
            <label class="block"><span class="field-label">Details</span><textarea name="details" rows="4" minlength="10" maxlength="2000" required class="field-control" placeholder="Tell us what happened and how we can help.">{{ old('details') }}</textarea></label>
            <button class="min-h-11 justify-self-start rounded-lg bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover">Send request</button>
        </form>
    @endif
</section>
