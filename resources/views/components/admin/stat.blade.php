@props(['value', 'label', 'tone' => 'neutral'])

<div class="rounded-xl bg-white p-5 ring-1 ring-stone-200">
    <p @class([
        'text-2xl font-black tracking-tight',
        'text-red-700' => $tone === 'danger',
        'text-amber-700' => $tone === 'warning',
        'text-stone-900' => ! in_array($tone, ['danger', 'warning'], true),
    ])>{{ $value }}</p>
    <p class="mt-0.5 text-sm text-stone-500">{{ $label }}</p>
</div>
