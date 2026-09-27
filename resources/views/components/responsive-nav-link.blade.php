@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block min-h-11 w-full border-l-4 border-emerald-700 bg-emerald-50 py-2 pe-4 ps-3 text-start text-base font-semibold text-emerald-900 transition duration-150 focus:outline-none focus:bg-emerald-100'
            : 'block min-h-11 w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-slate-600 transition duration-150 hover:border-slate-300 hover:bg-white hover:text-slate-950 focus:outline-none focus:bg-white focus:text-slate-950';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
