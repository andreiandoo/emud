{{-- The row's overflow menu. Closed on outside click and on Escape, because a menu that stays
     open while the operator works elsewhere covers the row underneath it. --}}
<div x-data="{ open: false }" class="relative flex justify-end" @keydown.escape="open = false">
    <button type="button" @click="open = ! open" @click.outside="open = false"
            :aria-expanded="open ? 'true' : 'false'"
            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900"
            aria-label="Alte acțiuni">···</button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute right-0 top-full z-20 mt-1 min-w-40 overflow-hidden rounded-lg border border-stone-200 bg-white py-1 shadow-lg">
        {{ $slot }}
    </div>
</div>
