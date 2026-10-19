<x-app-layout>
    @php($actionRoute = route($role === 'buyer' ? 'returns.update' : ($role === 'seller' ? 'seller.returns.update' : 'admin.returns.update'), $case))
    @php($deadline = $case->deadline())
    @php($overdue = $case->isOverdue())
    @php($statusCopy = match ($case->status) {
        'awaiting_seller' => 'The seller is reviewing the request.',
        'offered' => 'The seller offered a remedy. The buyer can accept it or ask JMMart to review it.',
        'rejected' => 'The seller rejected the request. The buyer can ask JMMart to review it.',
        'awaiting_return' => 'The buyer is sending the goods back to the seller.',
        'awaiting_receipt' => 'The goods are on their way back. The seller confirms receipt.',
        'awaiting_fulfillment' => 'The seller is completing the approved '.$case->remedy.'.',
        'awaiting_confirmation' => 'The buyer confirms the recorded outcome, or disputes it.',
        'appealed' => 'JMMart support is reviewing this case.',
        'completed' => 'This case is complete.',
        'denied' => 'JMMart denied this claim. The reasons are in the response below.',
        'closed' => 'JMMart closed this case because its deadline passed without a response.',
        default => '',
    })
    <div class="page-shell"><div class="page-container max-w-4xl py-8">
        <a href="{{ $role === 'buyer' ? route('orders.show', $case->sellerOrder->order) : route($role === 'seller' ? 'seller.returns.index' : 'admin.returns.index') }}" class="text-sm font-semibold text-brand underline">Back</a>
        <h1 class="mt-4 text-3xl font-bold text-ink">Return case</h1>
        <p class="mt-2 text-sm text-muted">
            @if ($role === 'admin')
                <a href="{{ route('admin.orders.show', $case->sellerOrder->order) }}" class="font-semibold text-brand underline">{{ $case->sellerOrder->order->number }}</a>
            @else
                {{ $case->sellerOrder->order->number }}
            @endif
            · {{ $case->sellerOrder->store->name }}@if ($role !== 'buyer') · buyer {{ $case->buyer->name }}@endif
        </p>
        @if (session('status'))<p class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-success" role="status">{{ session('status') }}</p>@endif
        @if ($errors->any())<div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <section class="mt-6 rounded-lg border {{ $overdue ? 'border-danger' : 'border-line' }} bg-surface p-5">
            <p class="font-semibold text-ink">{{ str($case->status)->replace('_', ' ')->headline() }}@if ($overdue)<span class="ml-2 rounded bg-red-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-danger">Overdue</span>@endif</p>
            <p class="mt-1 text-sm text-muted">{{ $statusCopy }}</p>
            @if ($deadline)<p class="mt-1 text-sm {{ $overdue ? 'font-semibold text-danger' : 'text-muted' }}">{{ $overdue ? 'Was due' : 'Due' }} {{ $deadline->format('M j, Y g:i A') }}.</p>@endif
        </section>
        <section class="mt-6 rounded-lg border border-line bg-surface p-5">
            <h2 class="font-semibold text-ink">Request</h2>
            <p class="mt-2 text-sm text-muted">{{ ucfirst(str_replace('_', ' ', $case->reason)) }} · preferred {{ $case->requested_remedy }}</p>
            <p class="mt-2 whitespace-pre-wrap text-sm text-ink">{{ $case->details }}</p>
            <ul class="mt-4 space-y-1 text-sm text-ink">@foreach ($case->items as $item)<li>{{ $item->orderItem->product_name }} · qty {{ $item->quantity }} · purchase price ₱{{ number_format($item->unit_price, 2) }}</li>@endforeach</ul>
            @if ($case->response_note)<p class="mt-4 whitespace-pre-wrap text-sm"><strong>Response:</strong> {{ $case->response_note }}</p>@endif
            @if ($case->remedy)
                <p class="mt-2 text-sm">Approved remedy: <strong>{{ ucfirst($case->remedy) }}</strong></p>
                @if ($case->remedy !== $case->requested_remedy)<p class="mt-1 text-sm font-semibold text-danger">This differs from the {{ $case->requested_remedy }} the buyer asked for.</p>@endif
            @endif
            @if ($case->return_waived)<p class="mt-2 text-sm">Physical return waived.</p>@elseif ($case->return_instructions)<p class="mt-2 whitespace-pre-wrap text-sm">Return instructions: {{ $case->return_instructions }}<br>No-cost method: {{ $case->return_method }}</p>@endif
            @if ($case->outcome?->kind === 'refund')<p class="mt-2 text-sm">Seller recorded refund: ₱{{ number_format($case->outcome->amount, 2) }} on {{ $case->outcome->paid_at?->format('M j, Y') }}.</p>@endif
            @if ($case->outcome?->kind === 'replacement')<p class="mt-2 text-sm">Replacement shipment: {{ $case->outcome->shipment_reference }}</p>@endif
        </section>
        <section class="mt-6 rounded-lg border border-line bg-surface p-5">
            <h2 class="font-semibold text-ink">Next action</h2>
            @if ($role === 'seller' && $case->status === 'awaiting_seller')
                <form method="POST" action="{{ $actionRoute }}" class="mt-4 grid gap-3">@csrf @method('PATCH')
                    <label>Response with reasons<textarea name="note" required minlength="10" maxlength="2000" class="field-control">{{ old('note') }}</textarea></label>
                    <label>Remedy<select name="remedy" class="field-control"><option value="refund" @selected(old('remedy', $case->requested_remedy) === 'refund')>Refund</option><option value="replacement" @selected(old('remedy', $case->requested_remedy) === 'replacement')>Replacement</option></select></label>
                    <label class="flex gap-2"><input type="checkbox" name="return_waived" value="1" @checked(old('return_waived'))> Waive physical return</label>
                    <label>Return instructions<textarea name="return_instructions" class="field-control">{{ old('return_instructions') }}</textarea></label>
                    <label>No-cost return method<input name="return_method" value="{{ old('return_method') }}" class="field-control" placeholder="Seller-paid pickup or prepaid drop-off"></label>
                    <div class="flex gap-3"><button name="action" value="offer" class="min-h-11 rounded-lg bg-brand px-4 text-white">Send offer</button><button name="action" value="reject" class="min-h-11 rounded-lg border border-line px-4">Reject with reason</button></div>
                </form>
            @elseif ($role === 'buyer' && in_array($case->status, ['offered', 'rejected'], true))
                <p class="mt-2 text-sm text-muted">Respond by {{ $case->buyer_respond_by?->format('M j, g:i A') }}. After that JMMart can close the case without a remedy.</p>
                @if ($case->status === 'offered')
                    <form method="POST" action="{{ $actionRoute }}" class="mt-3">@csrf @method('PATCH')
                        <button name="action" value="accept" class="min-h-11 rounded-lg bg-brand px-4 text-white">Accept {{ $case->remedy }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-3">@csrf @method('PATCH')
                    <label>Why should support review this?<textarea name="note" required minlength="10" maxlength="2000" class="field-control">{{ old('note') }}</textarea></label>
                    <button name="action" value="appeal" class="min-h-11 justify-self-start rounded-lg border border-line px-4">Appeal to JMMart</button>
                </form>
            @elseif ($role === 'buyer' && $case->status === 'awaiting_return')
                <p class="mt-2 text-sm text-muted">Send the goods back using the method above, then upload your proof. If you do not, JMMart can close the case without a remedy.</p>
                <form method="POST" action="{{ $actionRoute }}" enctype="multipart/form-data" class="mt-3 grid gap-3">@csrf @method('PATCH')
                    <label>Handoff proof (photo or PDF, up to 5 MB)<input type="file" name="proof" required accept="image/jpeg,image/png,image/webp,application/pdf" class="field-control"></label>
                    <button name="action" value="handoff" class="min-h-11 justify-self-start rounded-lg bg-brand px-4 text-white">Record handoff</button>
                </form>
            @elseif ($role === 'seller' && $case->status === 'awaiting_receipt')
                <form method="POST" action="{{ $actionRoute }}" class="mt-3">@csrf @method('PATCH')<button name="action" value="receive" class="min-h-11 rounded-lg bg-brand px-4 text-white">Record returned goods received</button></form>
            @elseif ($role === 'seller' && $case->status === 'awaiting_fulfillment' && $case->remedy === 'refund')
                <p class="mt-2 text-sm text-muted">Support coordinates the refund destination privately. Record proof only after payment. The original order total stays unchanged.</p>
                <form method="POST" action="{{ $actionRoute }}" enctype="multipart/form-data" class="mt-3 grid gap-3">@csrf @method('PATCH')
                    <label>Payment date<input type="date" name="paid_at" value="{{ old('paid_at') }}" required max="{{ now()->toDateString() }}" class="field-control"></label>
                    <label>Payment proof (photo or PDF, up to 5 MB)<input type="file" name="proof" required accept="image/jpeg,image/png,image/webp,application/pdf" class="field-control"></label>
                    <button name="action" value="refund" class="min-h-11 justify-self-start rounded-lg bg-brand px-4 text-white">Record seller-paid refund</button>
                </form>
            @elseif ($role === 'seller' && $case->status === 'awaiting_fulfillment' && $case->remedy === 'replacement')
                <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-3">@csrf @method('PATCH')
                    <label>Shipment reference<input name="reference" value="{{ old('reference') }}" required maxlength="255" class="field-control"></label>
                    <button name="action" value="ship" class="min-h-11 justify-self-start rounded-lg bg-brand px-4 text-white">Record replacement shipment</button>
                </form>
            @elseif ($role === 'buyer' && $case->status === 'awaiting_confirmation')
                <form method="POST" action="{{ $actionRoute }}" class="mt-3">@csrf @method('PATCH')
                    <button name="action" value="confirm" class="min-h-11 rounded-lg bg-brand px-4 text-white">Confirm receipt</button>
                </form>
                <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-3">@csrf @method('PATCH')
                    <label>Dispute details<textarea name="note" required minlength="10" maxlength="2000" class="field-control">{{ old('note') }}</textarea></label>
                    <button name="action" value="appeal" class="min-h-11 justify-self-start rounded-lg border border-line px-4">Dispute outcome</button>
                </form>
            @elseif ($role === 'admin' && $case->status === 'appealed' && $case->outcome)
                <p class="mt-2 text-sm text-muted">An outcome was recorded before this dispute. Verify it to close the case, or set it aside to put the seller back on the hook for the remedy.</p>
                <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-2">@csrf @method('PATCH')
                    <label>Verification evidence and decision<textarea name="note" required minlength="10" maxlength="2000" class="field-control">{{ old('note') }}</textarea></label>
                    <div class="flex flex-wrap gap-3">
                        <button name="action" value="verify" class="min-h-11 rounded-lg border border-line px-4">Verify completion</button>
                        <button name="action" value="reopen" class="min-h-11 rounded-lg bg-brand px-4 text-white">Set outcome aside and reopen</button>
                    </div>
                </form>
            @elseif ($role === 'admin' && $case->status === 'appealed')
                <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-3">@csrf @method('PATCH')
                    <label>Final decision<select name="decision" class="field-control"><option value="refund">Refund</option><option value="replacement">Replacement</option><option value="deny">Deny with reasons</option></select></label>
                    <label>Reasoned decision<textarea name="note" required minlength="10" maxlength="2000" class="field-control">{{ old('note') }}</textarea></label>
                    <label class="flex gap-2"><input type="checkbox" name="return_waived" value="1" @checked(old('return_waived'))> Waive physical return</label>
                    <label>Return instructions<textarea name="return_instructions" class="field-control">{{ old('return_instructions') }}</textarea></label>
                    <label>No-cost return method<input name="return_method" value="{{ old('return_method') }}" class="field-control"></label>
                    <button name="action" value="decide" class="min-h-11 justify-self-start rounded-lg bg-brand px-4 text-white">Record final decision</button>
                </form>
            @elseif ($role === 'admin' && $overdue && in_array($case->status, ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment', 'awaiting_confirmation'], true))
                <p class="mt-2 text-sm text-muted">This case passed its deadline. Take it over to decide it yourself.</p>
                <form method="POST" action="{{ $actionRoute }}" class="mt-3">@csrf @method('PATCH')<button name="action" value="takeover" class="min-h-11 rounded-lg bg-brand px-4 text-white">Take over overdue case</button></form>
                @if ($case->status === 'awaiting_confirmation' && $case->outcome)
                    <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-2">@csrf @method('PATCH')
                        <label>Verification evidence and decision<textarea name="note" required minlength="10" maxlength="2000" class="field-control">{{ old('note') }}</textarea></label>
                        <button name="action" value="verify" class="min-h-11 justify-self-start rounded-lg border border-line px-4">Verify completion</button>
                    </form>
                @endif
            @elseif ($role === 'admin' && $overdue && in_array($case->status, ['offered', 'rejected', 'awaiting_return'], true))
                <p class="mt-2 text-sm text-muted">The buyer did not act before the deadline. Closing ends the case without a remedy.</p>
                <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-2">@csrf @method('PATCH')
                    <label>Reason for closing<textarea name="note" required minlength="10" maxlength="2000" class="field-control">{{ old('note') }}</textarea></label>
                    <button name="action" value="close" class="min-h-11 justify-self-start rounded-lg border border-line px-4">Close unanswered case</button>
                </form>
            @elseif ($role === 'buyer' && $case->status === 'awaiting_seller' && $overdue)
                <p class="mt-2 text-sm text-muted">The seller did not respond in time. JMMart can decide the case instead.</p>
                <form method="POST" action="{{ $actionRoute }}" class="mt-3 grid gap-3">@csrf @method('PATCH')
                    <label>Why should support step in?<textarea name="note" required minlength="10" maxlength="2000" class="field-control">{{ old('note') }}</textarea></label>
                    <button name="action" value="appeal" class="min-h-11 justify-self-start rounded-lg bg-brand px-4 text-white">Ask JMMart to step in</button>
                </form>
            @elseif (in_array($case->status, ['completed', 'denied', 'closed'], true))
                <p class="mt-2 text-sm text-muted">This case is closed. Email support with the order number if something is still outstanding.</p>
            @else
                <p class="mt-2 text-sm text-muted">Waiting for the next party. JMMart can step in once the deadline above passes.</p>
            @endif
            @if ($role === 'seller' && $case->received_at)
                <h3 class="mt-6 font-semibold">Inspected resellable units</h3>
                @foreach ($case->items as $item)
                    @if ($item->quantity > $item->restocked_quantity)
                        <form method="POST" action="{{ $actionRoute }}" class="mt-2 flex items-end gap-3">@csrf @method('PATCH')
                            <input type="hidden" name="case_item_id" value="{{ $item->id }}">
                            <label class="text-sm">{{ $item->orderItem->product_name }} ({{ $item->quantity - $item->restocked_quantity }} remaining)<input type="number" name="quantity" min="1" max="{{ $item->quantity - $item->restocked_quantity }}" required class="field-control w-20"></label>
                            <button name="action" value="restock" class="min-h-11 rounded-lg border border-line px-4">Mark resellable</button>
                        </form>
                    @endif
                @endforeach
            @endif
        </section>
        @if ($role === 'buyer')
            <form method="POST" action="{{ route('messages.seller-orders.start', $case->sellerOrder) }}" class="mt-6">@csrf
                <button class="min-h-11 rounded-lg border border-brand px-4 text-sm font-semibold text-brand">Message {{ $case->sellerOrder->store->name }}</button>
            </form>
        @elseif ($role === 'seller')
            <p class="mt-6"><a href="{{ route('messages.index') }}" class="text-sm font-semibold text-brand underline">Message the buyer in your inbox</a></p>
        @endif
        <section class="mt-6 rounded-lg border border-line bg-surface p-5">
            <h2 class="font-semibold text-ink">Private evidence</h2>
            <p class="mt-1 text-sm text-muted">Visible to the buyer, the seller, and JMMart support only.</p>
            <ul class="mt-3 space-y-2 text-sm">@forelse ($case->evidence as $evidence)<li><a class="font-semibold text-brand underline" href="{{ route('returns.evidence', [$case, $evidence]) }}">{{ ucfirst($evidence->kind) }} · {{ $evidence->original_name }}</a><span class="text-muted"> · {{ $evidence->uploader?->name ?? 'Unknown' }} · {{ $evidence->created_at->format('M j, Y g:i A') }}</span></li>@empty<li class="text-muted">No files added.</li>@endforelse</ul>
        </section>
        <section class="mt-6 rounded-lg border border-line bg-surface p-5">
            <h2 class="font-semibold text-ink">Case history</h2>
            <ol class="mt-3 space-y-4">@foreach ($case->events as $event)<li class="border-l-2 border-brand pl-3 text-sm"><strong>{{ str($event->type)->replace('_', ' ')->headline() }}</strong> · {{ $event->actor?->name ?? 'JMMart' }} · {{ $event->created_at->format('M j, Y g:i A') }}@if ($event->note)<p class="mt-1 whitespace-pre-wrap text-muted">{{ $event->note }}</p>@endif</li>@endforeach</ol>
        </section>
    </div></div>
</x-app-layout>
