@props(['product', 'verdict' => null])

@php($image = $product->media->first())
@php($price = $product->variants->firstWhere('is_active', true)?->retail_price)

<a href="{{ route('storefront.product', $product) }}" class="group flex flex-col rounded-xl border border-stone-200 bg-white p-4 transition hover:border-stone-400">
    <div class="mb-3 flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-stone-100">
        @if($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) }}"
                 alt="{{ $image->alt_text ?? $product->name }}" class="h-full w-full object-cover">
        @else
            <span class="text-xs text-stone-400">Fără imagine</span>
        @endif
    </div>

    @if($product->brand)
        <span class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $product->brand->name }}</span>
    @endif
    <span class="mt-0.5 line-clamp-2 font-semibold group-hover:underline">{{ $product->name }}</span>

    @if($verdict)
        <span @class([
            'mt-2 inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-semibold',
            'bg-lime-100 text-lime-900' => $verdict->isCertain(),
            'bg-amber-100 text-amber-900' => ! $verdict->isCertain() && $verdict->fits(),
            'bg-stone-100 text-stone-600' => ! $verdict->fits(),
        ])>{{ $verdict->label() }}</span>
    @endif

    <span class="mt-auto pt-3 text-lg font-bold">
        {{ $price !== null ? \App\Support\Money::of($price, config('emud.catalog.default_currency', 'RON'))->format() : 'Preț la cerere' }}
    </span>
</a>
