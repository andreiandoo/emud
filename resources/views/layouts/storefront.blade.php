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

<footer class="mt-16 border-t border-stone-200 bg-white">
    <div class="shell space-y-4 py-8 text-xs text-stone-500">
        @php($footerPages = \App\Models\Page::query()->published()->where('show_in_footer', true)->orderBy('position')->orderBy('title')->get())
        @if($footerPages->isNotEmpty())
            <nav class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($footerPages as $footerPage)
                    <a href="{{ route('storefront.page', $footerPage->slug) }}" class="hover:text-stone-900 hover:underline">{{ $footerPage->title }}</a>
                @endforeach
                <a href="{{ route('storefront.contact') }}" class="hover:text-stone-900 hover:underline">Contact</a>
            </nav>
        @endif

        @php($social = collect($settings->array('social_links'))->filter())
        @if($social->isNotEmpty())
            <nav class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($social as $network => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="capitalize hover:text-stone-900 hover:underline">{{ $network }}</a>
                @endforeach
            </nav>
        @endif

        <p>{{ $settings->string('site_title', 'eMUD') }} · piese și accesorii 4x4, off-road și overlanding</p>
    </div>
</footer>
@livewireScripts
</body>
</html>
