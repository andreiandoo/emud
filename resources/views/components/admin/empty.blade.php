@props(['title' => 'Nimic aici încă', 'hint' => null])

{{-- An empty state says what to do next. A blank panel makes an operator wonder whether the
     page failed to load. --}}
<div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center">
    <p class="font-semibold text-stone-700">{{ $title }}</p>
    @if($hint)<p class="mt-1 text-sm text-stone-500">{{ $hint }}</p>@endif
    @isset($slot)<div class="mt-4">{{ $slot }}</div>@endisset
</div>
