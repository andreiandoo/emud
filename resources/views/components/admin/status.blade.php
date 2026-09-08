@props(['tone' => 'neutral', 'label'])

{{-- Status as a glyph plus a word, the way the reference shows Paid / Cancelled / Refunded.
     The glyph carries the meaning as much as the colour does, so the state is still readable
     for someone who cannot separate red from green. --}}
@php($glyphs = ['positive' => '✓', 'danger' => '✕', 'warning' => '↺', 'info' => '•', 'neutral' => '•'])

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 text-sm',
    'text-emerald-700' => $tone === 'positive',
    'text-red-700' => $tone === 'danger',
    'text-amber-700' => $tone === 'warning',
    'text-sky-700' => $tone === 'info',
    'text-stone-500' => $tone === 'neutral',
]) }}>
    <span aria-hidden="true">{{ $glyphs[$tone] ?? '•' }}</span>{{ $label }}
</span>
