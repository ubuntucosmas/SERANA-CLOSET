<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Apply saved theme before paint to avoid a light/dark flash -->
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('serana-theme');
                    var root = document.documentElement;
                    if (t === 'light') root.classList.remove('dark');
                    else root.classList.add('dark');
                } catch (e) {}
            })();
        </script>

        {{-- Block form on purpose: an inline @php(...) followed by a later @php/@endphp block compiles into a parse error. --}}
        @php
            $seo = $page['props']['seo'] ?? \App\Support\PageSeo::forRequest(request());
        @endphp
        <title inertia>{{ $seo['title'] }}</title>
        <meta inertia="description" name="description" content="{{ $seo['description'] }}">
        <meta inertia="robots" name="robots" content="{{ $seo['robots'] }}">
        <link inertia="canonical" rel="canonical" href="{{ $seo['url'] }}">
        @foreach(['title', 'description', 'url', 'type', 'image'] as $key)
            @if($seo[$key] ?? null)<meta inertia="og:{{ $key }}" property="og:{{ $key }}" content="{{ $seo[$key] }}">@endif
        @endforeach
        <meta inertia="twitter:card" name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
        @foreach(['title', 'description', 'image'] as $key)
            @if($seo[$key] ?? null)<meta inertia="twitter:{{ $key }}" name="twitter:{{ $key }}" content="{{ $seo[$key] }}">@endif
        @endforeach
        @if($seo['structured_data'])
            <script inertia="structured-data" type="application/ld+json">{!! json_encode($seo['structured_data'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
        @endif
        <meta name="theme-color" content="#07090e">

        <!-- Dynamic Favicon -->
        @if(isset($page['props']['theme_settings']['site_favicon']))
            @php
                $favicon = $page['props']['theme_settings']['site_favicon'];
                // If it's already a full URL (resolved by ThemeSetting model), use it directly
                // otherwise fall back to storage URL
                $faviconUrl = str_starts_with($favicon, 'http') ? $favicon : \Illuminate\Support\Facades\Storage::disk('public')->url($favicon);
            @endphp
            <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
        @endif
        
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <!-- Approved SERANA typography -->
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />

        <!-- Material Symbols (Icons) -->
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>
    <body class="font-body antialiased bg-background text-on-surface selection:bg-primary/25 selection:text-background">
        @inertia
    </body>
</html>
