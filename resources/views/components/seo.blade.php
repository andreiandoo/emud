@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'index' => true,
    'follow' => true,
    'type' => 'website',
])

{{-- The browser and search-result title, pushed rather than set through Livewire's ->title():
     the string is already written here, and a second copy on the component would drift from it
     the first time one of the two is edited. The layout reads this stack before printing its
     own fallback — the slot is rendered before the layout wrapper, which is the same reason
     the meta stack below works at all. --}}
@if($title)
    @push('page-title'){{ $title }}@endpush
@endif

{{-- Pushed into the layout's stack so every page states its own metadata instead of the layout
     guessing one description for the whole shop. --}}
@push('meta')
    @if($description)
        <meta name="description" content="{{ Str::limit(strip_tags($description), 160, '') }}">
        <meta property="og:description" content="{{ Str::limit(strip_tags($description), 160, '') }}">
    @endif
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if($image)<meta property="og:image" content="{{ $image }}">@endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta name="robots" content="{{ $index ? 'index' : 'noindex' }},{{ $follow ? 'follow' : 'nofollow' }}">
@endpush
