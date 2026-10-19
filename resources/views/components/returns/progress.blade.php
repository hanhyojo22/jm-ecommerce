@props(['case', 'role' => 'buyer'])

@php
    $status = $case->status;
    $terminal = in_array($status, ['completed', 'denied', 'closed'], true);

    $reviewCurrent = in_array($status, ['awaiting_seller', 'offered', 'rejected', 'appealed'], true);
    $reviewDone = $terminal || in_array($status, ['awaiting_return', 'awaiting_receipt', 'awaiting_fulfillment', 'awaiting_confirmation'], true);

    $returnDone = $case->return_waived || $case->received_at !== null || in_array($status, ['awaiting_fulfillment', 'awaiting_confirmation', 'completed'], true);
    $returnCurrent = ! $returnDone && in_array($status, ['awaiting_return', 'awaiting_receipt'], true);

    $remedyDone = $status === 'completed';
    $remedyCurrent = in_array($status, ['awaiting_fulfillment', 'awaiting_confirmation'], true);

    $step = function (string $state, string $label, ?string $copy = null): array {
        return ['state' => $state, 'label' => $label, 'copy' => $copy];
    };

    $reviewCopy = match (true) {
        $reviewCurrent && $status === 'awaiting_seller' => '48-hour response window',
        $reviewCurrent && $status === 'appealed' => 'Reviewing the appeal',
        $reviewCurrent => 'Buyer deciding next',
        $reviewDone => 'Decision on record',
        default => null,
    };

    $returnCopy = match (true) {
        $case->return_waived => 'No return needed',
        $returnCurrent && $status === 'awaiting_return' => 'Send the goods back',
        $returnCurrent && $status === 'awaiting_receipt' => 'On the way to the seller',
        $returnDone && $case->received_at !== null => 'Received '.$case->received_at->format('M j'),
        $returnDone => null,
        default => null,
    };

    $remedyLabel = $case->remedy ? ucfirst($case->remedy) : 'Remedy';
    $remedyCopy = match (true) {
        $remedyCurrent && $status === 'awaiting_fulfillment' => 'Seller completing it',
        $remedyCurrent && $status === 'awaiting_confirmation' && $role === 'buyer' => 'Confirm it below',
        $remedyCurrent => 'Awaiting buyer sign-off',
        $remedyDone => 'Recorded and confirmed',
        in_array($status, ['awaiting_return', 'awaiting_receipt'], true) => 'Once the goods arrive',
        default => null,
    };

    $steps = [
        $step('done', 'Request sent', 'Opened '.$case->created_at->format('M j')),
        $step($reviewDone ? 'done' : ($reviewCurrent ? 'current' : 'upcoming'), $status === 'appealed' && $reviewCurrent ? 'JMMart review' : 'Seller review', $reviewCopy),
        $step($case->return_waived ? 'done' : ($returnDone ? 'done' : ($returnCurrent ? 'current' : 'upcoming')), $case->return_waived ? 'Return waived' : 'Return', $returnCopy),
        $step($remedyDone ? 'done' : ($remedyCurrent ? 'current' : 'upcoming'), $remedyLabel, $remedyCopy),
    ];

    $waitingOn = match ($status) {
        'awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment' => 'seller',
        'offered', 'rejected', 'awaiting_confirmation', 'awaiting_return' => 'buyer',
        'appealed' => 'support',
        default => null,
    };
    $yourTurn = ($role === 'seller' && $waitingOn === 'seller')
        || ($role === 'buyer' && $waitingOn === 'buyer')
        || ($role === 'admin' && $waitingOn === 'support');
    $waitingLabel = ['seller' => 'the seller', 'buyer' => 'the buyer', 'support' => 'JMMart support'][$waitingOn] ?? null;
@endphp

<div {{ $attributes }}>
    <ol class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-4" aria-label="Case progress">
        @foreach ($steps as $progressStep)
            <li class="min-w-0" @if ($progressStep['state'] === 'current') aria-current="step" @endif>
                <div class="h-1.5 rounded-full {{ $progressStep['state'] === 'upcoming' ? 'bg-slate-200' : 'bg-brand' }}"></div>
                <p class="mt-3 flex items-center gap-1.5 text-xs font-semibold {{ $progressStep['state'] === 'upcoming' ? 'text-muted' : 'text-brand-hover' }}">
                    @if ($progressStep['state'] === 'done')
                        <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                    @elseif ($progressStep['state'] === 'current')
                        <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
                    @endif
                    {{ $progressStep['label'] }}
                </p>
                @if ($progressStep['copy'])
                    <p class="mt-1 text-[11px] leading-4 text-muted">{{ $progressStep['copy'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>

    @if ($terminal)
        @php
            $terminalCopy = match ($status) {
                'completed' => 'The remedy was confirmed and the case is complete. No further action is needed.',
                'denied' => 'JMMart denied this claim. The reasons are recorded in the decision below.',
                default => 'JMMart closed this case because its deadline passed without a response.',
            };
            $terminalClasses = match ($status) {
                'completed' => 'border-green-200 bg-green-50 text-success',
                'denied' => 'border-red-200 bg-red-50 text-danger',
                default => 'border-line bg-canvas text-muted',
            };
        @endphp
        <div class="mt-5 flex items-start gap-2.5 rounded-[10px] border px-3.5 py-2.5 text-sm {{ $terminalClasses }}">
            <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="m8.5 12 2.5 2.5L16 9.5" /></svg>
            <p>{{ $terminalCopy }}</p>
        </div>
    @else
        <div @class([
            'mt-5 flex items-start gap-2.5 rounded-[10px] border px-3.5 py-2.5 text-sm',
            'border-amber-200 bg-amber-50 text-warning-text' => $yourTurn,
            'border-line bg-canvas text-muted' => ! $yourTurn,
        ])>
            @if ($yourTurn)
                <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v4l3 2" /></svg>
                <p><strong class="font-semibold">Your turn.</strong> Take the next step in the action panel below.</p>
            @else
                <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v4l3 2" /></svg>
                <p>Waiting on <strong class="font-semibold">{{ $waitingLabel }}.</strong> JMMart support steps in once a deadline passes.</p>
            @endif
        </div>
    @endif
</div>
