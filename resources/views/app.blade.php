<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    @php
        $isLoginPage = request()->is('login');
        $loginTitle = 'LaundryHub | Masuk & Kelola Cucian Pelanggan';
        $loginDescription = 'Masuk ke LaundryHub untuk melacak pesanan, mengelola operasional laundry, dan memberi pelanggan pembaruan cucian dari satu tempat.';
        $loginUrl = url('/login');
        $socialImageUrl = asset('apple-touch-icon.png');
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => 'LaundryHub',
            'url' => $loginUrl,
            'description' => $loginDescription,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
        ];
    @endphp
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
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
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
        <meta name="theme-color" content="#0943a9">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @if ($isLoginPage)
            <title>{{ $loginTitle }}</title>
            <meta name="description" content="{{ $loginDescription }}">
            <link rel="canonical" href="{{ $loginUrl }}">
            <meta name="robots" content="index,follow">

            <meta property="og:title" content="{{ $loginTitle }}">
            <meta property="og:description" content="{{ $loginDescription }}">
            <meta property="og:type" content="website">
            <meta property="og:url" content="{{ $loginUrl }}">
            <meta property="og:image" content="{{ $socialImageUrl }}">
            <meta property="og:locale" content="id_ID">
            <meta property="og:site_name" content="LaundryHub">

            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="{{ $loginTitle }}">
            <meta name="twitter:description" content="{{ $loginDescription }}">
            <meta name="twitter:image" content="{{ $socialImageUrl }}">

            <script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
        @else
            <meta name="robots" content="noindex,nofollow">
            <x-inertia::head>
                <title>{{ config('app.name', 'LaundryHub') }}</title>
            </x-inertia::head>
        @endif
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
