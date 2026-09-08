@props(['title'])

{{-- The small uppercase heading from the reference's right rail. Kept as a component so the
     letter-spacing and colour stay identical everywhere they appear. --}}
<section {{ $attributes->class(['space-y-3']) }}>
    <div class="flex items-baseline justify-between gap-3">
        <h2 class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">{{ $title }}</h2>
        @isset($aside)<div class="text-sm text-stone-500">{{ $aside }}</div>@endisset
    </div>

    {{ $slot }}
</section>
