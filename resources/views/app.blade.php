<!DOCTYPE html>
@php($isStorefront = str_starts_with($page['component'], 'shop/') || str_starts_with($page['component'], 'account/'))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark', 'theme-space-issues' => $isStorefront])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
        @if(config('shop.channels.google_verification'))
            <meta name="google-site-verification" content="{{ config('shop.channels.google_verification') }}">
        @endif
        @if(config('shop.channels.meta_verification'))
            <meta name="facebook-domain-verification" content="{{ config('shop.channels.meta_verification') }}">
        @endif
        @if($isStorefront && config('shop.analytics.enabled'))
            {{-- Tells site-beacon.ts that anonymous statistics are on. --}}
            <meta name="site-statistics" content="on">
        @endif
        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

        @php($seo = $page['props']['seo'] ?? null)
        @if($seo)
            <meta name="description" content="{{ $seo['description'] }}">
            <meta name="robots" content="{{ $seo['index'] ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow' }}">
            <link rel="canonical" href="{{ $seo['canonical'] }}">
            @foreach($seo['alternates'] as $lang => $href)
                <link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">
            @endforeach
            <meta property="og:type" content="{{ $seo['type'] }}">
            <meta property="og:title" content="{{ $seo['title'] }}">
            <meta property="og:description" content="{{ $seo['description'] }}">
            <meta property="og:url" content="{{ $seo['canonical'] }}">
            <meta property="og:site_name" content="{{ config('app.name') }}">
            <meta property="og:locale" content="{{ $seo['og_locale'] }}">
            @foreach($seo['og_locale_alternates'] as $alternate)
                <meta property="og:locale:alternate" content="{{ $alternate }}">
            @endforeach
            @if($seo['image'])
                <meta property="og:image" content="{{ $seo['image'] }}">
                <meta property="og:image:alt" content="{{ $seo['title'] }}">
                <meta name="twitter:card" content="summary_large_image">
            @else
                <meta name="twitter:card" content="summary">
            @endif
            <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif

        {{-- Runs before first paint: system dark mode, and the visitor's saved accessibility preferences (see use-a11y-prefs.ts). --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }

                try {
                    Object.assign(document.documentElement.dataset, JSON.parse(localStorage.getItem('a11y') || '{}'));
                } catch (e) {}
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            html.theme-space-issues.dark {
                background-color: #000;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Instrument Sans is the admin's typeface; the storefront theme brings its own. --}}
        @unless($isStorefront)
            @fonts
        @endunless

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ isset($seo['title']) ? $seo['title'].' – '.config('app.name') : config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
