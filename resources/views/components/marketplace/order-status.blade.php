@props(['status', 'payment' => false])
{{-- Seller order and COD payment states share one colour language: amber waits on the seller,
     blue is in motion, green is finished, red stopped. Unpaid COD is not the seller's move
     until delivery, so a pending payment stays neutral. --}}
<span {{ $attributes->class('sc-badge') }} data-tone="{{ match (true) {
    $payment && $status === 'pending' => 'neutral',
    $status === 'pending' => 'warning',
    in_array($status, ['confirmed', 'processing', 'shipped'], true) => 'progress',
    in_array($status, ['delivered', 'paid'], true) => 'success',
    in_array($status, ['cancelled', 'failed'], true) => 'danger',
    default => 'neutral',
} }}">{{ $payment && $status === 'pending' ? 'Unpaid' : ucfirst($status) }}</span>
