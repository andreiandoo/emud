@props(['title', 'meta' => null, 'open' => false, 'name' => null])

{{-- One section of a product's details. A closed section keeps its content in the page, for
     search engines and for find-in-page; only the eye is spared the length. `name` gives it an
     anchor, and lets a link elsewhere open it with $dispatch('open-section', name). --}}
<div x-data="{ open: @js((bool) $open) }"
     @if($name) id="{{ $name }}" @open-section.window="if ($event.detail === @js($name)) open = true" @endif
     class="scroll-mt-32 border-t border-line">
    <h2>
        <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
                class="group flex w-full items-center justify-between gap-4 py-5 text-left">
            <span class="font-display text-[1.35rem] font-semibold tracking-[-.01em]">{{ $title }}</span>
            <span class="flex shrink-0 items-center gap-3">
                @if($meta)
                    <span class="font-mono text-[11px] uppercase tracking-[.1em] text-ink2">{{ $meta }}</span>
                @endif
                <span class="grid h-8 w-8 place-items-center rounded-full border border-line2 transition duration-500 group-hover:border-ink"
                      :class="open && 'rotate-45 border-ink bg-ink text-light'">
                    <x-storefront.icon name="plus" class="h-4 w-4" />
                </span>
            </span>
        </button>
    </h2>

    <div x-show="open" x-collapse @unless($open) x-cloak @endunless>
        <div class="pb-8">{{ $slot }}</div>
    </div>
</div>
