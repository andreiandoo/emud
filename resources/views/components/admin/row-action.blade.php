@props(['tone' => 'default'])

@php($isLink = $attributes->has('href'))

{{-- type="button" is not optional: a <button> inside a <form> defaults to submit, and these
     menus sit inside editor forms, where a "Remove" action would post the whole form as well as
     firing its own wire:click. --}}
<{{ $isLink ? 'a' : 'button' }}
    @unless($isLink) type="button" @endunless
    {{ $attributes->class([
        'block w-full px-3 py-1.5 text-left text-sm transition',
        'text-red-700 hover:bg-red-50' => $tone === 'danger',
        'text-stone-700 hover:bg-stone-100' => $tone !== 'danger',
    ]) }}>{{ $slot }}</{{ $isLink ? 'a' : 'button' }}>
