@props(['title', 'subtitle' => null])

{{-- The page title is the largest thing on the screen in the reference and carries no bold; the
     size does the work, so the weight does not have to. --}}
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1.5 text-sm text-stone-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
