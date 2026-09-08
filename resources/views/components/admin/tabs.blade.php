@props(['tabs' => [], 'current' => '', 'counts' => [], 'field' => 'tab'])

{{-- Soft grey pill for the active tab rather than an underline. These sit above a table whose
     header already carries a rule, and two horizontal lines that close together read as a mistake.
     Used for the coarse split of a list; the chips below handle the finer filtering, so the two
     never compete for the same job. --}}
<div {{ $attributes->class(['mb-4 flex flex-wrap items-center gap-1']) }}>
    @foreach($tabs as $key => $label)
        <button type="button" wire:click="$set('{{ $field }}', '{{ $key }}')" @class([
            'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition',
            'bg-stone-100 font-semibold text-stone-900' => (string) $current === (string) $key,
            'text-stone-500 hover:bg-stone-50 hover:text-stone-900' => (string) $current !== (string) $key,
        ])>
            {{ $label }}
            @isset($counts[$key])
                <span class="text-xs text-stone-400">{{ $counts[$key] }}</span>
            @endisset
        </button>
    @endforeach
</div>
