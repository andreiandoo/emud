@props(['name' => 'part'])

{{-- Inline paths rather than an icon package. The set is small, fixed, and half of it is
     automotive — no general-purpose icon library ships a winch or a leaf spring, so the
     dependency would have to be supplemented by hand anyway.

     Category icons are chosen in the back office by key; an unknown key falls back to a
     generic part rather than rendering nothing, so a mistyped key still leaves the menu
     aligned. --}}
@php($paths = [
    // Interface.
    'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4-4',
    'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
    'cart' => 'M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H6M10 21h.01M17 21h.01',
    'heart' => 'M12 20s-7-4.5-7-9.2A4 4 0 0 1 12 8a4 4 0 0 1 7-1.2c0 4.7-7 13.2-7 13.2z',
    'chevron-down' => 'M6 9l6 6 6-6',
    'chevron-right' => 'M9 6l6 6-6 6',
    'close' => 'M6 6l12 12M18 6L6 18',
    'menu' => 'M4 7h16M4 12h16M4 17h16',
    'phone' => 'M5 3h4l2 5-2.5 1.5a12 12 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z',
    'pin' => 'M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
    'plus' => 'M12 5v14M5 12h14',
    'check' => 'M5 13l4 4L19 7',
    'truck' => 'M3 7h11v9H3zM14 10h4l3 3v3h-7M6.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
    'car' => 'M4 16h16M5 16V9l2-4h10l2 4v7M7 19h2M15 19h2M6 12h12',
    'logout' => 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h11',

    // Catalogue.
    'suspension' => 'M8 3v18M16 3v18M8 6h8M8 10h8M8 14h8M8 18h8',
    'shock' => 'M12 3v4M12 17v4M9 7h6v10H9zM9 10h6M9 13h6',
    'wheel' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM12 3v6M12 15v6M3 12h6M15 12h6',
    'tyre' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 8l3 2M19 8l-3 2M5 16l3-2M19 16l-3-2',
    'brake' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM17 5l-3 4M20 14h-5',
    'engine' => 'M4 12h2V9h4V7h4v2h3l3 3v5H8l-2-2H4zM10 12h4M17 9V6',
    'exhaust' => 'M3 14h10a4 4 0 0 1 4 4v2M3 11h7M17 12h4M17 8h4M6 17h4',
    'cooling' => 'M5 4h14v16H5zM9 4v16M15 4v16M5 9h14M5 14h14',
    'transmission' => 'M6 6h.01M12 6h.01M18 6h.01M6 12h.01M12 12h.01M6 6v12M6 6h12M12 6v6M18 6v6M6 18h.01',
    'electrical' => 'M13 3L5 14h6l-1 7 8-11h-6z',
    'light' => 'M12 3a6 6 0 0 0-3 11v3h6v-3a6 6 0 0 0-3-11zM10 21h4',
    'body' => 'M3 15l2-6h14l2 6v3H3zM7 9V6h10v3M6 18v2M18 18v2',
    'interior' => 'M7 20V9a3 3 0 0 1 3-3h1a3 3 0 0 1 3 3v2h3a2 2 0 0 1 2 2v6M7 14h7',
    'winch' => 'M4 8h9a4 4 0 0 1 0 8H8M4 5v6M8 16l-3 3M8 16h4',
    'roof-rack' => 'M3 8h18M5 8v3M12 8v3M19 8v3M4 15h16v4H4zM8 15V8M16 15V8',
    'oil' => 'M12 3l5 7a5 5 0 1 1-10 0zM9 13a3 3 0 0 0 3 3',
    'filter' => 'M4 5h16l-6 7v6l-4 2v-8z',
    'tool' => 'M15 4a5 5 0 0 0-6.5 6.5L3 16v5h5l5.5-5.5A5 5 0 0 0 20 9l-3 3-3-3 3-3a5 5 0 0 0-2-2z',
    'offroad' => 'M4 17h16M6 17V8l3-3h6l3 3v9M8 20h2M14 20h2M4 11l2-2M20 11l-2-2M9 11h6',
    'part' => 'M12 3l8 4v10l-8 4-8-4V7zM4 7l8 4 8-4M12 11v10',

    // Social networks, drawn in the same stroke weight as everything else rather than pasted in
    // as brand glyphs: a row of filled logos next to outlined icons reads as a third-party
    // widget dropped into the footer.
    'facebook' => 'M15 3h-2a4 4 0 0 0-4 4v3H6v4h3v7h4v-7h3l1-4h-4V8a1 1 0 0 1 1-1h2z',
    'instagram' => 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zM12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM17.4 6.6h.01',
    'youtube' => 'M21.6 7.2a3 3 0 0 0-2.1-2.1C17.7 4.6 12 4.6 12 4.6s-5.7 0-7.5.5A3 3 0 0 0 2.4 7.2 31 31 0 0 0 2 12a31 31 0 0 0 .4 4.8 3 3 0 0 0 2.1 2.1c1.8.5 7.5.5 7.5.5s5.7 0 7.5-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 22 12a31 31 0 0 0-.4-4.8zM10 15V9l5 3z',
    'tiktok' => 'M16 4v10.5a4.5 4.5 0 1 1-4.5-4.5M16 4a5 5 0 0 0 5 5',
    'linkedin' => 'M4 9h3v11H4zM5.5 5.5h.01M10 20V9h3v1.6A3.4 3.4 0 0 1 20 13v7h-3v-6a2 2 0 0 0-4 0v6z',
    'twitter' => 'M5 4l14 16M19 4L5 20',
    'mail' => 'M3 6h18v12H3zM3 7l9 6 9-6',
    'arrow-right' => 'M4 12h16M14 6l6 6-6 6',
    'arrow-left' => 'M20 12H4M10 6l-6 6 6 6',
    'play' => 'M7 5l12 7-12 7z',
    'download' => 'M12 4v11M7 10l5 5 5-5M5 20h14',
    'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
    'star' => 'M12 3l2.7 5.6 6.1.8-4.4 4.2 1.1 6.1L12 16.8l-5.5 2.9 1.1-6.1-4.4-4.2 6.1-.8z',
    'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
    'vin' => 'M3 6h18v12H3zM7 10v4M10 10l1.5 4 1.5-4M16 10v4M16 10l2 4v-4',
    'gauge' => 'M4 16a8 8 0 1 1 16 0M12 16l4-5M8 20h8',
    'wrench' => 'M14.5 5.5a4 4 0 0 0-5.3 5L4 15.7a1.8 1.8 0 0 0 2.5 2.5l5.2-5.2a4 4 0 0 0 5-5.3l-2.4 2.4-2.3-.4-.4-2.3z',
    'return' => 'M4 9h11a5 5 0 0 1 0 10H9M8 5 4 9l4 4',
    'shield' => 'M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6zM9 12l2 2 4-4',
    'trash' => 'M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
    'minus' => 'M5 12h14',
    'box' => 'M4 7l8-4 8 4v10l-8 4-8-4zM4 7l8 4 8-4M12 11v10',
    'sliders' => 'M4 6h9M17 6h3M15 4v4M4 12h3M11 12h9M9 10v4M4 18h11M19 18h1M17 16v4',
    'expand' => 'M14 4h6v6M10 20H4v-6M20 4l-7 7M4 20l7-7',
    'building' => 'M4 20V6l8-3v17M12 9h8v11M8 8h.01M8 12h.01M8 16h.01M16 13h.01M16 17h.01M3 20h18',
])

{{-- Size comes from presentation attributes rather than a default class: a `class="h-4 w-4"`
     on the call site would merge with a default `h-5 w-5` and lose, because Tailwind resolves
     the pair by stylesheet order, not by who wrote it. A CSS class beats a width attribute, so
     this way the caller always wins. --}}
<svg {{ $attributes->merge(['width' => 20, 'height' => 20]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $paths[$name] ?? $paths['part'] }}" />
</svg>
