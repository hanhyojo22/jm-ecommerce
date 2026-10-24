@props(['number'])
{{-- Order numbers carry a 26-character ULID. Lists show the prefix and the last eight characters,
     which are what tells orders apart; the full number stays in the tooltip and for screen readers. --}}
@php($short = mb_strlen($number) > 16 ? Str::before($number, '-').'-…'.Str::substr($number, -8) : $number)
<span {{ $attributes->class('whitespace-nowrap') }} title="{{ $number }}">@if ($short === $number){{ $number }}@else<span aria-hidden="true">{{ $short }}</span><span class="sr-only">{{ $number }}</span>@endif</span>
