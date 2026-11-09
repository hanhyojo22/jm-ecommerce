<x-app-layout :error-summary="false">
    <x-slot name="header"><h1 class="marketplace-page-title">Order requests</h1></x-slot>
    <div class="mx-auto max-w-6xl space-y-5 px-4 py-8 sm:px-8">
        @if(session('status'))<p role="status" class="rounded-lg bg-blue-50 p-4 text-sm text-brand-hover">{{ session('status') }}</p>@endif
        @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-danger">{{ $errors->first() }}</div>@endif
        <nav class="flex flex-wrap gap-2" aria-label="Request status">
            @foreach(['open' => 'Open', 'resolved' => 'Resolved', 'rejected' => 'Rejected'] as $status => $label)
                <a href="{{ route('admin.order-issues.index', ['status' => $status]) }}" @class(['rounded-lg border px-4 py-2 text-sm font-semibold', 'border-brand bg-blue-50 text-brand-hover' => ($filters['status'] ?? 'open') === $status, 'border-line bg-surface text-muted' => ($filters['status'] ?? 'open') !== $status])>{{ $label }}</a>
            @endforeach
        </nav>
        @forelse($issues as $issue)
            <article class="rounded-lg border border-line bg-surface p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="font-semibold text-ink">{{ str($issue->type)->replace('_', ' ')->title() }} · {{ $issue->sellerOrder->store->name }}</h2><p class="mt-1 text-sm text-muted"><a class="text-brand-hover underline" href="{{ route('admin.orders.show', $issue->sellerOrder->order) }}">Order #{{ $issue->sellerOrder->order->number }}</a> · {{ $issue->buyer->name }} ({{ $issue->buyer->email }}) · {{ $issue->created_at->format('M j, Y') }}</p>@if($issue->type === 'failed_delivery' && $issue->status === 'open')@php($refusals = $issue->buyer->codRefusalCount())<p class="mt-1 text-sm {{ $refusals > 0 ? 'font-semibold text-danger' : 'text-muted' }}">Reported by the store. This buyer has {{ trans_choice('{0} no confirmed COD refusals|{1} :count confirmed COD refusal|[2,*] :count confirmed COD refusals', $refusals) }} in the last {{ config('marketplace.cod_refusals.window_days') }} days.</p>@endif</div>
                    <span class="rounded-full bg-canvas px-3 py-1 text-xs font-semibold text-muted">{{ ucfirst($issue->status) }}</span>
                </div>
                <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-ink">{{ $issue->details }}</p>
                @if($issue->status === 'open')
                    <form method="POST" action="{{ route('admin.order-issues.update', $issue) }}" class="mt-5 grid gap-3 border-t border-line pt-5 sm:grid-cols-[1fr_auto]">
                        @csrf @method('PATCH')
                        <label class="sm:col-span-2"><span class="field-label">Response to buyer</span><textarea name="resolution_note" rows="3" minlength="10" maxlength="2000" required class="field-control" placeholder="Explain the decision and next steps."></textarea></label>
                        <label><span class="field-label">Decision</span><select name="decision" class="field-control" required><option value="resolved">Resolve (no order change)</option><option value="rejected">Reject request</option>@if($issue->type === 'cancellation' && in_array($issue->sellerOrder->status, ['pending', 'confirmed'], true))<option value="cancel_order">Cancel store order and restore stock</option>@elseif($issue->type === 'failed_delivery' && $issue->sellerOrder->status === 'shipped')<option value="cancel_order">Buyer refused or unreachable: cancel, restore stock, count as a COD refusal</option><option value="cancel_order_courier">Courier problem: cancel and restore stock, don't count against the buyer</option>@endif</select></label>
                        <button class="min-h-11 self-end rounded-lg bg-brand px-5 text-sm font-semibold text-white hover:bg-brand-hover">Save decision</button>
                    </form>
                @else
                    <p class="mt-4 rounded-lg bg-canvas p-3 text-sm text-muted">Response: {{ $issue->resolution_note }}</p>
                @endif
            </article>
        @empty
            <p class="rounded-lg border border-line bg-surface p-8 text-center text-muted">No {{ $filters['status'] ?? 'open' }} requests.</p>
        @endforelse
        {{ $issues->links() }}
    </div>
</x-app-layout>
