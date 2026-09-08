@props(['title', 'subtitle' => null])

{{-- The detail card: title, an action row, then content. Actions sit directly under the title
     rather than in a corner, which is where the reference puts them and where they stay
     reachable when the panel is narrow. --}}
<div {{ $attributes->class(['card-padded space-y-5']) }}>
    <div>
        <h2 class="text-lg font-semibold tracking-tight">{{ $title }}</h2>
        @if($subtitle)<p class="mt-0.5 text-sm text-stone-500">{{ $subtitle }}</p>@endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset

    {{ $slot }}
</div>
