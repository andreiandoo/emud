@props(['tone' => 'default'])

<{{ $attributes->has('href') ? 'a' : 'button' }}
    {{ $attributes->class([
        'block w-full px-3 py-1.5 text-left text-sm transition',
        'text-red-700 hover:bg-red-50' => $tone === 'danger',
        'text-stone-700 hover:bg-stone-100' => $tone !== 'danger',
    ]) }}>{{ $slot }}</{{ $attributes->has('href') ? 'a' : 'button' }}>
