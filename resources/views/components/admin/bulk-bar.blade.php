@props(['count' => 0, 'clear' => null])

{{-- Floating action bar for a multi-row selection, as in the reference. Fixed to the viewport
     rather than the table: with a long list the selection is usually made at the top and the
     actions would otherwise scroll out of reach. --}}
<div x-show="{{ $count }} > 0" x-cloak x-transition
     class="pointer-events-none fixed inset-x-0 bottom-6 z-30 flex justify-center px-4">
    <div class="pointer-events-auto flex items-center gap-1 rounded-xl bg-stone-950 py-2 pl-2 pr-3 text-sm text-white shadow-lg">
        @if($clear)
            <button wire:click="{{ $clear }}" class="rounded-lg px-2 py-1.5 text-stone-400 hover:bg-stone-800 hover:text-white" aria-label="Deselectează">✕</button>
        @endif

        <span class="px-2 text-stone-300">Selectate: <span class="font-semibold text-white">{{ $count }}</span></span>

        <div class="flex items-center gap-1">{{ $slot }}</div>
    </div>
</div>
