<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle() }}</title>
        <meta name="description" content="{{ $metaDescription() }}">
        <link rel="canonical" href="{{ $canonicalUrl() }}">
        @if($noindex)
            <meta name="robots" content="noindex, follow">
        @else
            <meta name="robots" content="index, follow, max-image-preview:large">
        @endif
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'Starboard') }}">
        <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
        <meta property="og:title" content="{{ $pageTitle() }}">
        <meta property="og:description" content="{{ $metaDescription() }}">
        <meta property="og:url" content="{{ $canonicalUrl() }}">
        <meta property="og:image" content="{{ asset('logo.png') }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="800">
        <meta property="og:image:height" content="800">
        <meta property="og:image:alt" content="{{ config('app.name', 'Starboard') }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $pageTitle() }}">
        <meta name="twitter:description" content="{{ $metaDescription() }}">
        <meta name="twitter:image" content="{{ asset('logo.png') }}">
        <meta name="twitter:image:alt" content="{{ config('app.name', 'Starboard') }}">
        <meta name="theme-color" content="#0f172a">
        <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('logo.png') }}" type="image/x-icon">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="min-h-dvh bg-gray-100 font-sans antialiased dark:bg-gray-900">
        <div class="flex min-h-dvh flex-col">
            <x-navigation />

            <main class="mx-auto w-full max-w-7xl flex-1 px-4 pb-12 pt-24 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>

            <x-footer />
        </div>
    </body>
</html>
