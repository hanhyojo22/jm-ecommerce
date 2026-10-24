@php
    $steps = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
    $currentStep = array_search($sellerOrder->status, array_keys($steps), true);
@endphp
{{-- Order progress. Finished steps carry a tick and the current one a dot, so the state never rests on colour alone. Cancelled orders have no progress to show. --}}
@if ($currentStep !== false)
    <ol @class(['sc-steps', $class ?? null]) aria-label="Order progress, step {{ $currentStep + 1 }} of {{ count($steps) }}">
        @foreach ($steps as $label)
            @php($state = $loop->index < $currentStep || $sellerOrder->status === 'delivered' ? 'done' : ($loop->index === $currentStep ? 'current' : 'upcoming'))
            <li class="sc-step is-{{ $state }}" @if ($loop->index === $currentStep) aria-current="step" @endif>
                <span class="sc-step-bar"></span>
                <span class="sc-step-label">@if ($state === 'done')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>@elseif ($state === 'current')<span class="sc-step-dot" aria-hidden="true"></span>@endif<span class="truncate">{{ $label }}</span>@if ($state === 'done')<span class="sr-only">, done</span>@endif</span>
            </li>
        @endforeach
    </ol>
@endif
