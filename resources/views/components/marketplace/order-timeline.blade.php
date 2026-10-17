@props(['status', 'label' => 'Delivery progress'])

@php
    /*
     * The five statuses an order moves through, in order. A cancelled order left the line
     * rather than stopping somewhere on it, so it gets the badge and no timeline at all.
     */
    $steps = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
    $current = array_search($status, array_keys($steps), true);
@endphp

@if ($current !== false)
    <ol {{ $attributes->merge(['class' => 'flex min-w-0 justify-between gap-2']) }} aria-label="{{ $label }}">
        @foreach (array_values($steps) as $index => $stepLabel)
            @php($reached = $index <= $current)
            <li class="relative flex min-w-0 flex-1 flex-col items-center gap-1.5 text-center text-[11px] font-medium leading-4 {{ $reached ? 'text-brand-hover' : 'text-muted' }}" @if ($index === $current) aria-current="step" @endif>
                @unless ($loop->first)
                    <span aria-hidden="true" class="absolute right-1/2 top-[9px] h-0.5 w-full {{ $reached ? 'bg-brand' : 'bg-slate-200' }}"></span>
                @endunless
                <span aria-hidden="true" class="z-[1] grid size-5 shrink-0 place-items-center rounded-full text-[10px] font-semibold {{ $reached ? 'bg-brand text-white' : 'bg-slate-200 text-muted' }}">{{ $index < $current ? '✓' : $index + 1 }}</span>
                <span class="min-w-0">{{ $stepLabel }}</span>
            </li>
        @endforeach
    </ol>
@endif
