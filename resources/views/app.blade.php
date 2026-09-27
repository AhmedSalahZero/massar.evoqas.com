{{-- ═══════════════════════════════════════════════════════════════
     Massar — Root HTML page (every Inertia page renders inside it)
     Location: resources/views/app.blade.php

     · lang / dir come from the current locale, so Arabic pages are
       right-to-left from the very first paint (no flash of LTR).
     · The theme class ("light") is set from the signed-in user's saved
       theme on the server, so there is no flash of the wrong theme
       either. Guests start dark (Massar default).
     · Fonts: Inter (English) + Tajawal (Arabic), as app.css expects.
     · @routes exposes Laravel's named routes to Vue (Ziggy: route()).
     ═══════════════════════════════════════════════════════════════ --}}
@php
    $locale = app()->getLocale();
    // Staff: their saved theme. Guests: dark in the app; the public site
    // for job seekers (Step 10) starts light. A guest's own choice is kept
    // in the browser (usePreferences) and wins after the first paint.
    $theme  = auth()->user()?->theme ?? (request()->routeIs('home', 'seeker.*') ? 'light' : 'dark');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" class="{{ $theme === 'light' ? 'light' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0C1829">

        <title inertia>{{ config('app.name', 'Massar') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
