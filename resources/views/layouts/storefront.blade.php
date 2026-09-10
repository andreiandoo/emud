<!doctype html>
{{-- The class the overlay scrollbar keys on. Only the shop gets it: the back office keeps its
     native scrollbars, where operators live in long tables and a familiar control beats a
     prettier one. The motion script keys on it too. --}}
<html lang="ro" class="storefront">
<head>
    {{-- Inside <head> rather than above the doctype: whitespace before a doctype is enough to
         put some browsers into quirks mode. --}}
    @php($settings = app(\App\Settings\StoreSettings::class))
    @php($faviconPath = $settings->string('favicon_path'))

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0e0f11">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Three sources, narrowest first: whatever the component set through ->title(), then the
         title its <x-seo> stated, then the shop tagline. Before this, every page in the shop
         shared one <title>, which is what a search engine sees as the name of the page. --}}
    @php($seoTitle = trim($__env->yieldPushContent('page-title')))
    <title>{{ $title ?? ($seoTitle !== '' ? $seoTitle : $settings->string('site_tagline', 'Piese și accesorii 4x4')) }} · {{ $settings->string('site_title', 'eMUD') }}</title>
    @if($faviconPath !== '')
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath) }}">
    @endif
    @stack('meta')
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-light font-sans text-ink antialiased">
<a href="#continut" class="sr-only z-50 rounded-[3px] bg-signal px-4 py-2.5 font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
    Sari la conținut
</a>

{{-- Pages with a full-bleed hero ask for the bar to float over it:
     #[Layout('layouts::storefront', ['fullWidth' => true, 'overlayHeader' => true])]. --}}
<x-storefront.header :overlay="$overlayHeader ?? false" />

{{-- Pages that carry a full-bleed band — a collection hero, a strip with its own ground —
     opt out of the page gutter with #[Layout('layouts::storefront', ['fullWidth' => true])] and
     wrap their own sections in .shell. Breaking out with 100vw margins instead would overflow
     by the width of the scrollbar, and the usual overflow-x:hidden cure kills position:sticky. --}}
<main id="continut" class="{{ ($fullWidth ?? false) ? '' : 'shell pb-24 pt-10' }}">{{ $slot }}</main>

<x-storefront.footer />
@livewireScripts
</body>
</html>
