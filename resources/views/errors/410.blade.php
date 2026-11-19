<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>JMMart is now on mobile · JMMart</title>
    <style>
        body{margin:0;background:#f7f8f7;color:#18332c;font:16px/1.6 system-ui,sans-serif;min-height:100vh;display:grid;place-items:center}main{box-sizing:border-box;width:min(100%,40rem);padding:2rem}h1{font-size:clamp(1.6rem,5vw,2.2rem);line-height:1.2;margin:.5rem 0 1rem}p{margin:0 0 1rem}a,button{display:inline-block;box-sizing:border-box;min-height:48px;padding:.7rem 1rem;border:1px solid #006b58;border-radius:.65rem;color:#006b58;background:white;font:inherit;text-decoration:none;cursor:pointer;margin:.25rem .5rem .25rem 0}small{display:block;overflow-wrap:anywhere;margin-top:1.5rem;color:#5b6f68}a:focus-visible,button:focus-visible{outline:3px solid #006b58;outline-offset:3px}
    </style>
</head>
<body>
    <main>
        <p>JMMart · 410</p>
        <h1>Shop with us on the JMMart mobile app</h1>
        <p>The JMMart web store is no longer available. Browsing, cart, checkout, orders, and buyer messages on the website have moved to the mobile app.</p>
        @auth
            @if (auth()->user()->isPlatformAdministrator())
                <a href="{{ route('dashboard') }}">Open admin workspace</a>
            @elseif (auth()->user()->account_type === 'seller' && auth()->user()->sellerTenant() !== null)
                <a href="{{ route('seller.dashboard') }}">Open Seller Center</a>
            @endif
        @else
            <a href="{{ route('login') }}">Seller or administrator sign in</a>
        @endauth
        <small>Reference: {{ request()->attributes->get('request_id') }}</small>
    </main>
</body>
</html>
