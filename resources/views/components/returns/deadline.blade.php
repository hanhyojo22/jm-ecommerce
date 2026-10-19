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
@endphp

@if ($deadline === null)
    <span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-muted ring-1 ring-inset ring-line']) }}>
        <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M8 12h8" /></svg>
        No fixed deadline
    </span>
@else
    <span {{ $attributes->class([
        'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
        'bg-red-50 text-danger ring-red-200' => $overdue,
        'bg-amber-50 text-warning-text ring-amber-200' => $dueSoon,
        'bg-slate-100 text-muted ring-line' => ! $overdue && ! $dueSoon,
    ]) }}>
        @if ($overdue)
            <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
        @else
            <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
        @endif
        @if ($overdue)
            Overdue · was due <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('M j, g:i A') }}</time>
        @elseif ($dueSoon)
            Due soon · <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('M j, g:i A') }}</time>
        @else
            Due <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('M j, g:i A') }}</time>
        @endif
    </span>
@endif
