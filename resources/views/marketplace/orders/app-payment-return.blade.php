<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $paid ? 'Payment sent' : 'Payment not finished' }} · JMMart</title>
    <style>
        body{margin:0;background:#f7f8f7;color:#18332c;font:16px/1.6 system-ui,sans-serif;min-height:100vh;display:grid;place-items:center}main{box-sizing:border-box;width:min(100%,40rem);padding:2rem}h1{font-size:clamp(1.6rem,5vw,2.2rem);line-height:1.2;margin:.5rem 0 1rem}p{margin:0 0 1rem}
    </style>
</head>
<body>
    <main>
        <p>JMMart</p>
        @if ($paid)
            <h1>Payment sent</h1>
            <p>Go back to the JMMart app to see your order. It shows as paid once GCash or Maya confirms the payment, usually within a minute.</p>
        @else
            <h1>Payment not finished</h1>
            <p>You were not charged. Go back to the JMMart app and open your order to try again.</p>
        @endif
        <p>You can close this tab.</p>
    </main>
</body>
</html>
