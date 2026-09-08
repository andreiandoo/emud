@props(['name' => '', 'size' => 'md'])

@php($initials = \Illuminate\Support\Str::of($name)->trim()->explode(' ')->filter()
    ->take(2)->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->implode(''))

{{-- Initials rather than a generated image: the back office lists people who never uploaded a
     photo, and a coloured circle with two letters is recognisable at a glance without inventing
     a face for them. --}}
<span {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center rounded-full bg-stone-200 font-semibold text-stone-600',
    'h-7 w-7 text-[11px]' => $size === 'sm',
    'h-8 w-8 text-xs' => $size === 'md',
    'h-10 w-10 text-sm' => $size === 'lg',
]) }}>{{ $initials ?: '—' }}</span>
