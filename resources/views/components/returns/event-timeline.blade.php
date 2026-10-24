@props(['case'])

@php
    /*
     * The case log. Every recorded event keeps the same shape: what happened, who did it,
     * when, and the note they left. Icons and tones come from one map so the same event
     * always looks the same wherever the case is read.
     */
    $types = [
        'submitted' => ['Request submitted', 'send', 'brand'],
        'admin_opened' => ['Opened by JMMart', 'shield', 'violet'],
        'offer' => ['Remedy offered', 'tag', 'brand'],
        'reject' => ['Request rejected', 'x-circle', 'amber'],
        'accept' => ['Offer accepted', 'check', 'brand'],
        'appeal' => ['Escalated to JMMart', 'flag', 'violet'],
        'handoff' => ['Goods handed over', 'truck', 'brand'],
        'receive' => ['Returned goods received', 'box', 'brand'],
        'refund' => ['Refund recorded', 'cash', 'green'],
        'ship' => ['Replacement shipped', 'truck', 'green'],
        'decide' => ['JMMart decided the appeal', 'scale', 'violet'],
        'takeover' => ['JMMart took over', 'shield', 'violet'],
        'reopen' => ['Outcome set aside', 'rotate', 'amber'],
        'confirm' => ['Outcome confirmed', 'check', 'green'],
        'verify' => ['Completion verified', 'check', 'green'],
        'close' => ['Case closed', 'lock', 'slate'],
        'evidence_added' => ['Evidence added', 'paperclip', 'slate'],
        'reminder' => ['Deadline reminder sent', 'bell', 'slate'],
        'restock' => ['Resellable units restocked', 'box', 'slate'],
        'replacement_stock_reduced' => ['Replacement stock reserved', 'box', 'slate'],
        'replacement_stock_restored' => ['Reserved stock returned', 'box', 'slate'],
    ];

    $tones = [
        'brand' => 'bg-blue-50 text-brand-hover',
        'violet' => 'bg-violet-50 text-violet-700',
        'green' => 'bg-green-50 text-success',
        'amber' => 'bg-amber-50 text-warning-text',
        'slate' => 'bg-slate-100 text-muted',
    ];

    $detailFor = function ($event): ?string {
        $data = $event->data ?? [];

        return match ($event->type) {
            'refund' => isset($data['refund_amount'])
                ? '₱'.number_format((float) $data['refund_amount'], 2).(isset($data['paid_at']) ? ' · payment dated '.\Illuminate\Support\Carbon::parse($data['paid_at'])->format('M j, Y') : '')
                : null,
            'ship' => isset($data['shipment_reference']) ? 'Shipment reference: '.$data['shipment_reference'] : null,
            'restock', 'replacement_stock_reduced', 'replacement_stock_restored' => isset($data['quantity']) ? $data['quantity'].' unit(s)' : null,
            default => null,
        };
    };
@endphp

<ol class="space-y-0">
    @forelse ($case->events as $event)
        @php
            [$label, $icon, $tone] = $types[$event->type] ?? [str($event->type)->replace('_', ' ')->headline(), 'dot', 'slate'];
            $actorRole = $event->actor === null
                ? 'JMMart'
                : ($event->actor->id === $case->buyer_id ? 'Buyer' : ($event->actor->isPlatformAdministrator() ? 'JMMart support' : 'Seller'));
            $detail = $detailFor($event);
        @endphp
        <li class="relative flex gap-3.5 {{ $loop->last ? '' : 'pb-5' }}">
            @unless ($loop->last)
                <span class="absolute left-4 top-9 h-[calc(100%-2rem)] w-px bg-line" aria-hidden="true"></span>
            @endunless
            <span class="grid size-8 shrink-0 place-items-center rounded-full {{ $tones[$tone] }}" aria-hidden="true">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    @switch($icon)
                        @case('send')<path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4z" />@break
                        @case('shield')<path d="M12 3 5 6v5c0 4.6 2.8 8 7 10 4.2-2 7-5.4 7-10V6z" /><path d="m9 12 2 2 4-4" />@break
                        @case('tag')<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.83z" /><path d="M7 7h.01" />@break
                        @case('x-circle')<circle cx="12" cy="12" r="9" /><path d="m15 9-6 6M9 9l6 6" />@break
                        @case('check')<circle cx="12" cy="12" r="9" /><path d="m8.5 12 2.5 2.5L16 9.5" />@break
                        @case('flag')<path d="M5 21V4" /><path d="M5 5h9l1 2h5v8h-7l-1-2H5" />@break
                        @case('truck')<path d="M3 6h11v9H3z" /><path d="M14 9h4l3 3v3h-7z" /><circle cx="7" cy="18" r="1.5" /><circle cx="17" cy="18" r="1.5" />@break
                        @case('box')<path d="m12 3 9 5v8l-9 5-9-5V8z" /><path d="m3 8 9 5 9-5M12 13v8" />@break
                        @case('cash')<rect x="3" y="6" width="18" height="12" rx="2" /><path d="M3 10h18M7 15h3" />@break
                        @case('scale')<path d="M12 4v16M7 20h10" /><path d="M6 8 12 4l6 4" /><path d="M6 8l-3 6a3.5 3.5 0 0 0 6 0z" /><path d="M18 8l-3 6a3.5 3.5 0 0 0 6 0z" />@break
                        @case('rotate')<path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v5h5" />@break
                        @case('lock')<rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" />@break
                        @case('paperclip')<path d="M21.4 11.05l-9.15 9.15a5.5 5.5 0 0 1-7.78-7.78l9.2-9.2a3.7 3.7 0 0 1 5.23 5.23l-9.2 9.2a1.85 1.85 0 0 1-2.61-2.62l8.48-8.48" />@break
                        @case('bell')<path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 7H3s3 0 3-7" /><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0" />@break
                        @default<circle cx="12" cy="12" r="3" />
                    @endswitch
                </svg>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <p class="text-sm font-semibold text-ink">{{ $label }}</p>
                    <span class="rounded-full bg-canvas px-2 py-0.5 text-xs font-semibold text-muted ring-1 ring-inset ring-line">{{ $actorRole }}</span>
                </div>
                <p class="mt-0.5 text-xs text-muted">
                    <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y · g:i A') }}</time>
                    @if ($event->actor)
                        · {{ $event->actor->name }}
                    @endif
                </p>
                @if ($detail)
                    <p class="mt-1 text-xs text-muted">{{ $detail }}</p>
                @endif
                @if ($event->note)
                    <p class="mt-2 whitespace-pre-wrap rounded-[10px] border border-line bg-canvas px-3 py-2 text-sm leading-6 text-ink">{{ $event->note }}</p>
                @endif
            </div>
        </li>
    @empty
        <li class="text-sm text-muted">No events recorded yet.</li>
    @endforelse
</ol>
