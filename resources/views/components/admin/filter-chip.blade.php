@props(['active' => false, 'count' => null])

{{-- Pill filters, matching the reference: outlined when idle, solid when they are actually
     narrowing the list. The count sits inside the chip so the operator can see a filter is
     doing something without opening it. --}}
<button type="button" {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition',
    'border-stone-900 bg-stone-900 text-white' => $active,
    'border-stone-300 bg-white text-stone-700 hover:border-stone-900 hover:text-stone-900' => ! $active,
]) }}>
    {{ $slot }}

    @if($count !== null)
        <span @class([
            'rounded-full px-1.5 text-xs font-semibold',
            'bg-white/20' => $active,
            'bg-stone-100 text-stone-600' => ! $active,
        ])>{{ $count }}</span>
    @endif
</button>
