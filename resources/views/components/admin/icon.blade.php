@props(['name' => 'grid'])

{{-- Inline rather than an icon package: the set is small and fixed, and a dependency for
     fourteen paths would cost more than it saves. --}}
@php($paths = [
    'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
    'receipt' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
    'undo' => 'M4 9h11a5 5 0 0 1 0 10H9M4 9l4-4M4 9l4 4',
    'users' => 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 20a6 6 0 0 1 12 0M17 11a3 3 0 1 0 0-6M16 20a6 6 0 0 1 6-6',
    'box' => 'M12 3l8 4v10l-8 4-8-4V7zM4 7l8 4 8-4M12 11v10',
    'tree' => 'M6 4h12M6 4v6a2 2 0 0 0 2 2h8a2 2 0 0 1 2 2v4M10 20h8',
    'sliders' => 'M4 7h16M4 12h16M4 17h16M9 5v4M15 10v4M7 15v4',
    'tag' => 'M3 12l9-9 9 9-9 9zM8 8h.01',
    'image' => 'M3 5h18v14H3zM3 16l5-5 4 4 3-3 6 6',
    'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4-4',
    'car' => 'M4 16h16M5 16V9l2-4h10l2 4v7M7 19h2M15 19h2M6 12h12',
    'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    'alert' => 'M12 4l9 16H3zM12 10v4M12 17h.01',
    'link' => 'M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1',
    'download' => 'M12 3v12M7 11l5 5 5-5M4 20h16',
    'history' => 'M3 12a9 9 0 1 0 3-6.7M3 4v5h5M12 8v5l3 2',
    'database' => 'M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zM4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
    'key' => 'M15 9a4 4 0 1 0-3.5 4L10 15l-2 2-3-1 1-3 5.5-5.5A4 4 0 0 0 15 9z',
    'truck' => 'M3 7h11v9H3zM14 10h4l3 3v3h-7M6.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
    'refresh' => 'M20 12a8 8 0 0 1-13.7 5.7M4 12a8 8 0 0 1 13.7-5.7M4 8V4M4 8h4M20 16v4M20 16h-4',
    'shuffle' => 'M4 7h4l8 10h4M4 17h4l2-2.5M16 7h4M18 5l2 2-2 2M18 15l2 2-2 2',
    'calculator' => 'M6 3h12v18H6zM9 7h6M9 11h.01M12 11h.01M15 11h.01M9 15h.01M12 15h.01M15 15h.01M9 19h6',
    'file' => 'M6 3h8l4 4v14H6zM14 3v4h4M9 12h6M9 16h6',
    'book' => 'M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2zM8 7h6M8 11h6',
    'wrench' => 'M15 4a5 5 0 0 0-6.5 6.5L3 16v5h5l5.5-5.5A5 5 0 0 0 20 9l-3 3-3-3 3-3a5 5 0 0 0-2-2z',
    'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.5 19.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 7.5l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z',
    'logout' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
])

<svg {{ $attributes->merge(['class' => 'h-4 w-4 shrink-0']) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true">
    <path d="{{ $paths[$name] ?? $paths['grid'] }}" />
</svg>
