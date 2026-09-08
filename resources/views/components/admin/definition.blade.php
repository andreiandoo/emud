@props(['rows' => [], 'cols' => 'label'])

{{-- The label/value list from the reference's detail panel: muted label in a fixed column, value
     aligned beside it, so a stack of them reads as a table without becoming one.

     The column split is a named variant rather than a class passed in: two grid-cols utilities on
     one element resolve by stylesheet order, and a class assembled from a variable never appears
     in the source Tailwind scans, so it would simply not be generated. --}}
@php($grid = match ($cols) {
    'value' => 'grid-cols-[1fr_auto]',
    'wide' => 'grid-cols-[12rem_1fr]',
    default => 'grid-cols-[9rem_1fr]',
})

<dl {{ $attributes->class(['grid gap-x-4 gap-y-2 text-sm', $grid]) }}>
    @foreach($rows as $label => $value)
        <dt class="text-stone-500">{{ $label }}</dt>
        <dd @class(['text-stone-900', 'text-right font-medium tabular-nums' => $cols === 'value'])>{!! $value === null || $value === '' ? '<span class="text-stone-400">&mdash;</span>' : e($value) !!}</dd>
    @endforeach

    {{ $slot }}
</dl>
