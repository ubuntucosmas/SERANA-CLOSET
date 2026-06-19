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

        <!-- SEO & Meta Tags -->
        <title inertia>{{ config('app.name', 'Serana Closet') }}</title>
        <meta name="description" content="Serana Closet: The Digital Atelier. Uncompromising craftsmanship merging traditional Kenyan tailoring with futuristic precision. Bespoke commissions and limited batch drops.">
        
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
        
        <!-- Serana Brand Fonts — WOODNORK Dark Luxury System -->
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,500&family=Manrope:wght@200..800&display=swap" rel="stylesheet" />

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
