@php
    $isLandingPage = request()->is('/');
    $component = $page['component'];
    $isWorkspace = $component === 'Dashboard' || str_starts_with($component, 'cms/') || str_starts_with($component, 'mobile/') || str_starts_with($component, 'settings/') || str_starts_with($component, 'auth/');
    $landingTitle = 'LaundryHub | Aplikasi Laundry, Antar-Jemput, QRIS & Multi-Cabang';
    $landingDescription = 'LaundryHub menyatukan antrean laundry, antar-jemput, cabang terdekat, pembayaran QRIS dinamis, WhatsApp, printer thermal, dan laporan multi-cabang.';
    $landingUrl = url('/');
    $socialImageUrl = asset('apple-touch-icon.png');
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'LaundryHub',
        'url' => $landingUrl,
        'description' => $landingDescription,
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web, Android',
        'featureList' => [
            'Antrean laundry',
            'Penjemputan dan cabang terdekat',
            'Pembayaran QRIS dinamis',
            'Notifikasi WhatsApp',
            'Printer thermal',
            'Laporan multi-cabang',
        ],
    ];
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-workspace-style="{{ $isWorkspace ? 'stitch' : 'default' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <style>
            html {
                color-scheme: light;
                background-color: #f6faf8;
            }

            html[data-workspace-style='stitch'] {
                background-color: #f8f9fb;
            }
        </style>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
        <meta name="theme-color" content="{{ $isWorkspace ? '#f8f9fb' : '#103d2e' }}">
        <meta name="color-scheme" content="light">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @if ($isLandingPage)
            <title>{{ $landingTitle }}</title>
            <meta name="description" content="{{ $landingDescription }}">
            <link rel="canonical" href="{{ $landingUrl }}">
            <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">

            <meta property="og:title" content="{{ $landingTitle }}">
            <meta property="og:description" content="{{ $landingDescription }}">
            <meta property="og:type" content="website">
            <meta property="og:url" content="{{ $landingUrl }}">
            <meta property="og:image" content="{{ $socialImageUrl }}">
            <meta property="og:image:width" content="180">
            <meta property="og:image:height" content="180">
            <meta property="og:image:alt" content="Ikon mesin cuci LaundryHub">
            <meta property="og:locale" content="id_ID">
            <meta property="og:site_name" content="LaundryHub">

            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="{{ $landingTitle }}">
            <meta name="twitter:description" content="{{ $landingDescription }}">
            <meta name="twitter:image" content="{{ $socialImageUrl }}">
            <meta name="twitter:image:alt" content="Ikon mesin cuci LaundryHub">

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
