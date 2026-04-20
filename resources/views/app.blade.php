<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
        
        <!-- Serana Brand Fonts — Satoshi Edition -->
        <link href="https://api.fontshare.com/v2/css?f[]=satoshi@900,700,500,300,400&display=swap" rel="stylesheet">
        
        <!-- Atelier Payment Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

        <!-- Material Symbols (Icons) -->
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-body font-light antialiased bg-[#050505] text-[#e5e2e1] selection:bg-[#B9C3FF]/20 selection:text-[#092484]">
        @inertia
    </body>
</html>
