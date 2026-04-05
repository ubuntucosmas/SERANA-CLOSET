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
            <link rel="icon" type="image/png" href="/storage/{{ $page['props']['theme_settings']['site_favicon'] }}">
        @endif
        
        <!-- Serana Brand Fonts — Curator Edition -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,300;0,400;1,300&family=Space+Grotesk:wght@300;400;500;600&display=swap" rel="stylesheet"/>

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
