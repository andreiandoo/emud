@props(['lat', 'lng', 'zoom' => 16, 'label' => 'Hartă'])

{{-- A map made of OpenStreetMap's raster tiles, laid out on the server: nine images and a pin.
     No script and no WebGL, so it shows in every browser. The embedded OpenStreetMap page draws
     with WebGL now, and where a browser has that switched off it leaves a warning in place of the
     map. The point sits in the middle of the frame whatever its size. --}}
@php
    $n = 2 ** $zoom;
    $x = ((float) $lng + 180) / 360 * $n;
    $latRad = deg2rad((float) $lat);
    $y = (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $n;
    $tileX = (int) floor($x);
    $tileY = (int) floor($y);
    // Where the point falls inside the 3 × 3 block of tiles, in pixels from its top left corner.
    $offsetX = (int) round(($x - $tileX + 1) * 256);
    $offsetY = (int) round(($y - $tileY + 1) * 256);
@endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden bg-[#e8e4da]']) }} role="img" aria-label="{{ $label }}">
    <div class="absolute grid grid-cols-3" style="width: 768px; height: 768px; left: calc(50% - {{ $offsetX }}px); top: calc(50% - {{ $offsetY }}px);">
        @foreach([-1, 0, 1] as $dy)
            @foreach([-1, 0, 1] as $dx)
                <img src="https://tile.openstreetmap.org/{{ $zoom }}/{{ ($tileX + $dx + $n) % $n }}/{{ $tileY + $dy }}.png"
                     alt="" width="256" height="256" loading="lazy" decoding="async" draggable="false"
                     class="block h-64 w-64 max-w-none select-none">
            @endforeach
        @endforeach
    </div>

    {{-- The pin's tip, not its middle, marks the spot. --}}
    <span class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-full text-signal drop-shadow-[0_2px_3px_rgba(0,0,0,.35)]" aria-hidden="true">
        <svg width="30" height="38" viewBox="0 0 30 38"><path d="M15 37s13-11.6 13-22A13 13 0 0 0 2 15c0 10.4 13 22 13 22z" fill="currentColor" stroke="#16181b" stroke-width="1.5" /><circle cx="15" cy="15" r="5" fill="#fff" /></svg>
    </span>
</div>
