{{--
    Installable-web-app head tags, shared by every document that owns a `<head>`
    (app, guest and public layouts plus the standalone `welcome` page).

    `site.webmanifest` pins `scope` to `/`. Without a manifest iOS derives a
    home-screen app's scope from the launch URL's own path, so an icon added
    from `/dashboard` sends every other page (`/network-sources`, `/lists/...`)
    back out to Safari with its chrome visible. The Apple meta tags stay
    alongside it for iOS below 16.4, which reads them instead of the manifest —
    and they are only read from the page the user installs from, which is why
    every layout must carry this component rather than just the dashboard's.
--}}
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Starboard') }}">
<meta name="theme-color" content="#0f172a">
<link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
<link rel="shortcut icon" href="{{ asset('logo.png') }}" type="image/x-icon">
