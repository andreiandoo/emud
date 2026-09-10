<!doctype html>
<html lang="ro">
<head>
    {{-- Inside <head> rather than above the doctype: whitespace before a doctype is enough to
         put some browsers into quirks mode. --}}
    @php($settings = app(\App\Settings\StoreSettings::class))
    @php($faviconPath = $settings->string('favicon_path'))

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $settings->string('site_tagline', 'Piese și accesorii 4x4') }} · {{ $settings->string('site_title', 'eMUD') }}</title>
    @if($faviconPath !== '')
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath) }}">
    @endif
    @stack('meta')
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
<x-storefront.header />

<main class="shell py-8">{{ $slot }}</main>

<x-storefront.footer />
@livewireScripts
</body>
</html>
