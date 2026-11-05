<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Request could not be completed · JMMart</title>
    <style>
        body{margin:0;background:#f7f8f7;color:#18332c;font:16px/1.6 system-ui,sans-serif;min-height:100vh;display:grid;place-items:center}main{box-sizing:border-box;width:min(100%,40rem);padding:2rem}h1{font-size:clamp(1.6rem,5vw,2.2rem);line-height:1.2}a,button{display:inline-block;box-sizing:border-box;min-height:48px;padding:.7rem 1rem;border:1px solid #006b58;border-radius:.65rem;color:#006b58;background:white;font:inherit;text-decoration:none;cursor:pointer;margin:.25rem .5rem .25rem 0}small{display:block;overflow-wrap:anywhere;margin-top:1.5rem}a:focus-visible,button:focus-visible{outline:3px solid #006b58;outline-offset:3px}
    </style>
</head>
<body>
    <main>
        <p>JMMart · {{ $status }}</p>
        <h1>{{ match ($status) { 404 => 'We could not find that page', 419 => 'Your session has expired', 429 => 'Please wait a moment', default => 'We could not complete this request' } }}</h1>
        <p>{{ match ($status) { 404 => 'The page may have moved or is no longer available.', 419 => 'Refresh the page and sign in again before continuing.', 429 => 'Too many requests were sent. Wait a moment before trying again.', default => 'The service may be temporarily unavailable. Please try again later.' } }}</p>
        @if (! request()->isMethodSafe() || $status >= 500)
            <p>Check your orders before trying a purchase again.</p>
        @endif
        <a href="{{ route('home') }}">Marketplace</a>
        @if (request()->isMethodSafe())
            <button type="button" onclick="window.location.reload()">Try again</button>
        @endif
        <a href="{{ route('orders.index') }}">Check orders</a>
        <small>Reference: {{ request()->attributes->get('request_id') }}</small>
    </main>
</body>
</html>
