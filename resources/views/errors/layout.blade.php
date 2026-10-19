<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Serana Closet</title>
    <style>
        body{margin:0;background:#07090e;color:#e2e8f0;font:16px/1.6 system-ui,sans-serif;min-height:100vh;display:grid;place-items:center;padding:24px;box-sizing:border-box}
        main{max-width:560px;padding:32px;background:#111622;border:1px solid #263044;border-radius:24px}
        .brand{color:#00d2ff;font-size:13px;letter-spacing:.12em}h1{font-size:32px;line-height:1.2}p{color:#b5c1d1}a{color:#00d2ff}nav{display:flex;flex-wrap:wrap;gap:20px;margin-top:28px}.primary{padding:12px 24px;background:#0062ff;color:white;border-radius:999px;text-decoration:none}a:focus-visible{outline:2px solid #00d2ff;outline-offset:5px}
    </style>
</head>
<body>
    <main>
        <a class="brand" href="{{ route('home') }}">SERANA CLOSET</a>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <nav aria-label="Next steps"><a class="primary" href="{{ route('home') }}">Return Home</a><a href="{{ route('contact') }}">Contact Support</a><a href="{{ route('track') }}">Track Order</a></nav>
    </main>
</body>
</html>
