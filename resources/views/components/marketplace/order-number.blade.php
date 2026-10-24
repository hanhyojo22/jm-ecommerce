@props(['number'])
{{-- The full number stays in the tooltip and for screen readers. --}}
@php($short = \App\Models\Order::shortNumber($number))
<span {{ $attributes->class('whitespace-nowrap') }} title="{{ $number }}">@if ($short === $number){{ $number }}@else<span aria-hidden="true">{{ $short }}</span><span class="sr-only">{{ $number }}</span>@endif</span>
