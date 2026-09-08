@props(['segments' => []])

@php($total = max(1, collect($segments)->sum('value')))

{{-- A single segmented bar with a legend, as in the reference's order-status block. One bar
     rather than one per status: the point is the split between them, which separate bars make
     the reader work out. --}}
<div class="space-y-3">
    <div class="flex h-1.5 overflow-hidden rounded-full bg-stone-100">
        @foreach($segments as $segment)
            <div class="{{ $segment['class'] ?? 'bg-stone-400' }}"
                 style="width: {{ round(($segment['value'] / $total) * 100, 2) }}%"></div>
        @endforeach
    </div>

    <ul class="space-y-1.5 text-sm">
        @foreach($segments as $segment)
            <li class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-sm {{ $segment['class'] ?? 'bg-stone-400' }}" aria-hidden="true"></span>
                <span class="flex-1 text-stone-600">{{ $segment['label'] }}</span>
                <span class="font-medium tabular-nums">{{ round(($segment['value'] / $total) * 100) }}%</span>
            </li>
        @endforeach
    </ul>
</div>
