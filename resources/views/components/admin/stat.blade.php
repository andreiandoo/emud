@props(['value', 'label', 'tone' => 'neutral'])

{{-- Grey ground rather than a bordered white card, matching the reference: these are read as a
     row of figures, and giving each one a border makes the row look like a table of one-cell
     tables. --}}
<div {{ $attributes->class(['rounded-xl bg-stone-100 p-5']) }}>
    <p @class([
        'text-2xl font-semibold tracking-tight tabular-nums',
        'text-red-700' => $tone === 'danger',
        'text-amber-700' => $tone === 'warning',
        'text-stone-900' => ! in_array($tone, ['danger', 'warning'], true),
    ])>{{ $value }}</p>
    <p class="mt-1 text-sm text-stone-500">{{ $label }}</p>
</div>
