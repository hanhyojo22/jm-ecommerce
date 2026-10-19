@props(['status'])

@php
    /*
     * One label and tone per case status, shared by the queues and the case page so both
     * read the same. Blue marks work in progress, amber a decision owed by one side,
     * violet JMMart review, green a granted remedy, red a denial, and slate a closed case.
     */
    $styles = [
        'awaiting_seller' => ['Awaiting seller response', 'bg-blue-50 text-brand-hover ring-blue-200', 'bg-brand-hover'],
        'offered' => ['Offer awaiting buyer', 'bg-amber-50 text-warning-text ring-amber-200', 'bg-warning'],
        'rejected' => ['Rejection awaiting buyer', 'bg-amber-50 text-warning-text ring-amber-200', 'bg-warning'],
        'awaiting_confirmation' => ['Awaiting buyer confirmation', 'bg-amber-50 text-warning-text ring-amber-200', 'bg-warning'],
        'awaiting_return' => ['Return in progress', 'bg-blue-50 text-brand-hover ring-blue-200', 'bg-brand-hover'],
        'awaiting_receipt' => ['Return in transit', 'bg-blue-50 text-brand-hover ring-blue-200', 'bg-brand-hover'],
        'awaiting_fulfillment' => ['Remedy in progress', 'bg-blue-50 text-brand-hover ring-blue-200', 'bg-brand-hover'],
        'appealed' => ['With JMMart support', 'bg-violet-50 text-violet-700 ring-violet-200', 'bg-violet-600'],
        'completed' => ['Completed', 'bg-green-50 text-success ring-green-200', 'bg-success'],
        'denied' => ['Claim denied', 'bg-red-50 text-danger ring-red-200', 'bg-danger'],
        'closed' => ['Closed without remedy', 'bg-slate-100 text-muted ring-line', 'bg-muted'],
    ];

    [$label, $classes, $dot] = $styles[$status]
        ?? [str($status)->replace('_', ' ')->headline(), 'bg-slate-100 text-muted ring-line', 'bg-muted'];
@endphp

<span {{ $attributes->class(['status-badge', $classes]) }}>
    <span class="mr-1.5 size-1.5 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $label }}
</span>
