@props(['case'])

@php
    /*
     * The deadline chip answers one question at a glance: is anyone on the clock, and is
     * that clock running out? Text carries the urgency, and the icon and colour repeat it
     * for people who scan rather than read.
     */
    $deadline = $case->deadline();
    $overdue = $case->isOverdue();
    $dueSoon = $deadline !== null && ! $overdue && $deadline->lessThan(now()->addDay());
    $relative = $deadline?->diffForHumans(now(), [
        'parts' => 1,
        'short' => true,
        'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
    ]);
@endphp

@if ($deadline === null)
    <span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-muted ring-1 ring-inset ring-line']) }}>
        <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M8 12h8" /></svg>
        No fixed deadline
    </span>
@else
    <span {{ $attributes->class([
        'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset',
        'bg-red-50 text-danger ring-red-200' => $overdue,
        'bg-amber-50 text-warning-text ring-amber-200' => $dueSoon,
        'bg-white text-muted ring-line' => ! $overdue && ! $dueSoon,
    ]) }}>
        @if ($overdue)
            <x-returns.icon name="alert" class="size-3.5 shrink-0" />
        @else
            <x-returns.icon name="clock" class="size-3.5 shrink-0" />
        @endif
        @if ($overdue)
            Overdue by {{ $relative }} · was due <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('M j, g:i A') }}</time>
        @elseif ($dueSoon)
            Due in {{ $relative }} · <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('M j, g:i A') }}</time>
        @else
            Due in {{ $relative }} · <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('M j, g:i A') }}</time>
        @endif
    </span>
@endif
