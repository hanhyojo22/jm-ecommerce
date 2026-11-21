@props(['order', 'stack' => false])
@if ($order->fulfillment_method !== 'pickup' && $order->delivery_latitude !== null && $order->delivery_longitude !== null)
    @php($pin = rawurlencode($order->delivery_latitude.','.$order->delivery_longitude))
    <div @class(['sc-delivery-actions' => $stack, 'mt-3 flex flex-wrap gap-2' => ! $stack])>
        <a href="https://www.google.com/maps/dir/?api=1&amp;destination={{ $pin }}"
           target="_blank" rel="noopener noreferrer"
           @class($stack ? 'sc-delivery-primary' : 'inline-flex min-h-11 items-center rounded-md border border-brand px-3 text-sm font-semibold text-brand hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand')>
            Open delivery pin in Maps
        </a>
        {{-- Street-level photos of the house; Google has no coverage on some small streets. --}}
        <a href="https://www.google.com/maps/@?api=1&amp;map_action=pano&amp;viewpoint={{ $pin }}"
           target="_blank" rel="noopener noreferrer"
           @class($stack ? 'sc-delivery-secondary' : 'inline-flex min-h-11 items-center rounded-md border border-brand px-3 text-sm font-semibold text-brand hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand')>
            View street
        </a>
    </div>
@endif
