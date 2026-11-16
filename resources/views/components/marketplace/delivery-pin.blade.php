@props(['order'])
@if ($order->fulfillment_method !== 'pickup' && $order->delivery_latitude !== null && $order->delivery_longitude !== null)
    <a href="https://www.google.com/maps/dir/?api=1&amp;destination={{ rawurlencode($order->delivery_latitude.','.$order->delivery_longitude) }}"
       target="_blank" rel="noopener noreferrer"
       class="mt-3 inline-flex min-h-11 items-center rounded-md border border-brand px-3 text-sm font-semibold text-brand hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
        Open delivery pin in Maps
    </a>
@endif
