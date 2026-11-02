@php
    $steps = [
        ['label' => 'Placed', 'date' => $sellerOrder->created_at],
        ['label' => 'Confirmed', 'date' => null],
        ['label' => 'Processing', 'date' => null],
        ['label' => 'Shipped', 'date' => $sellerOrder->shipped_at],
        ['label' => 'Delivered', 'date' => $sellerOrder->delivered_at],
    ];
    $currentStep = array_search($sellerOrder->status, ['pending', 'confirmed', 'processing', 'shipped', 'delivered'], true);
@endphp
{{-- Order progress. Finished steps carry a tick and the current one a dot, so the state never
     rests on colour alone. Dates come only from the timestamps the journey actually recorded. --}}
@if ($currentStep !== false)
    <ol @class(['sc-steps', $class ?? null]) aria-label="Order progress, step {{ $currentStep + 1 }} of {{ count($steps) }}">
        @foreach ($steps as $step)
            @php
                $isFinal = $loop->last;
                $isDone = $loop->index < $currentStep || $sellerOrder->status === 'delivered';
                $state = $isDone ? 'done' : ($loop->index === $currentStep ? 'current' : 'upcoming');
            @endphp
            <li @class([
                'sc-step',
                'is-'.$state,
                'is-final' => $isFinal && $state === 'done',
            ]) @if ($loop->index === $currentStep) aria-current="step" @endif>
                <span class="sc-step-node" aria-hidden="true">
                    @if ($state === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                    @endif
                </span>
                <span class="sc-step-label">
                    {{ $step['label'] }}@if ($state === 'done')<span class="sr-only">, done</span>@elseif ($state === 'current')<span class="sr-only"> (current step)</span>@endif
                </span>
                @if ($step['date'])
                    <span class="sc-step-date">{{ $step['date']->format('M j') }}</span>
                @endif
            </li>
        @endforeach
    </ol>
@endif
