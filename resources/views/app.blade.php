<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- The v2 preference starts returning visitors in light mode too; later choices are remembered. -->
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('serana-theme-v2');
                    var root = document.documentElement;
                    root.classList.toggle('dark', t === 'dark');
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
            {{-- Already a browser URL on the configured public disk (ThemeSetting::get). --}}
            <link rel="icon" type="image/png" href="{{ $page['props']['theme_settings']['site_favicon'] }}">
        @endif
        
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <!-- Approved SERANA typography -->
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />

        <!-- Material Symbols (Icons) -->
        {{-- Icon font is subset to the glyphs the app uses (~28KB vs 1.1MB). Adding a
             new icon? Add its name here (alphabetical) or it renders as text. --}}
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&icon_names=account_balance_wallet,account_circle,add,add_photo_alternate,add_shopping_cart,architecture,archive,arrow_back,arrow_forward,arrow_right_alt,assignment,auto_awesome,backspace,badge,barcode_scanner,bolt,chat,check,check_circle,checkroom,chevron_left,chevron_right,close,content_cut,dark_mode,dashboard,delete,delete_forever,design_services,download,draw,dynamic_feed,edit,expand_less,expand_more,gesture,group,handshake,home,hourglass_top,image,inbox,inventory,inventory_2,keyboard_arrow_down,light_mode,local_florist,local_shipping,lock,logout,mail,menu,more_horiz,north_east,package_2,paid,pause,payments,photo_camera,photo_library,pin_drop,play_arrow,point_of_sale,precision_manufacturing,progress_activity,receipt_long,search,search_off,settings,shield,shopping_bag,shopping_cart,smartphone,storefront,straighten,support_agent,sync,task_alt,tune,verified,verified_user,visibility,warning,whatsapp&display=swap" rel="stylesheet">

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
