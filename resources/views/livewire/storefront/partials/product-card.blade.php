@props(['product', 'verdict' => null])

@php($image = $product->media->first())
@php($price = $product->variants->firstWhere('is_active', true)?->retail_price)

{{-- One link for the whole card: the target is large, and a card with several small targets
     inside it is a worse thing to tap on a phone. The fit verdict is written out, not only
     coloured, so it reads the same to someone who cannot tell green from grey. --}}
<a href="{{ route('storefront.product', $product) }}"
   class="group st-lift flex h-full flex-col overflow-hidden rounded-[3px] border border-line bg-white text-ink hover:border-line2">
    <div class="relative aspect-square overflow-hidden border-b border-line bg-[radial-gradient(70%_62%_at_50%_40%,#ffffff_0%,#f2efe8_62%,#e6e0d3_100%)]">
        @if($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) }}"
                 alt="{{ $image->alt_text ?? $product->name }}" loading="lazy"
                 class="h-full w-full object-contain p-6 mix-blend-multiply transition duration-[1100ms] ease-[cubic-bezier(.2,.8,.2,1)] group-hover:scale-105">
        @else
            <span class="absolute inset-0 grid place-items-center text-line2" aria-hidden="true">
                <x-storefront.icon name="part" class="h-14 w-14" />
            </span>
            <span class="sr-only">Fără imagine</span>
        @endif

        @if($verdict && $verdict->fits())
            <span @class([
                'absolute left-3 top-3 rounded-[2px] px-2 py-1 text-[10px] font-semibold uppercase tracking-[.1em]',
                'bg-fit text-white' => $verdict->isCertain(),
                'bg-sand text-sandink' => ! $verdict->isCertain(),
            ])>{{ $verdict->isCertain() ? 'Compatibil' : 'Verifică' }}</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-1.5 p-4 sm:p-5">
        @if($product->brand)
            <span class="font-mono text-[11px] uppercase tracking-[.1em] text-ink2">{{ $product->brand->name }}</span>
        @endif

        <span class="line-clamp-2 text-[15.5px] font-semibold leading-snug">{{ $product->name }}</span>

        @if($verdict)
            <span @class([
                'mt-0.5 inline-flex items-center gap-1.5 text-[13px] font-medium',
                'text-fit' => $verdict->isCertain(),
                'text-amber-700' => ! $verdict->isCertain() && $verdict->fits(),
                'text-ink2' => ! $verdict->fits(),
            ])>
                <x-storefront.icon :name="$verdict->fits() ? 'check' : 'close'" class="h-3.5 w-3.5 shrink-0" />
                {{ $verdict->label() }}
            </span>
        @endif

        <span class="mt-auto flex items-end justify-between gap-3 pt-4">
            <span class="font-display text-[1.45rem] font-semibold leading-none tracking-tight tabular-nums">
                {{ $price !== null ? \App\Support\Money::of($price, config('emud.catalog.default_currency', 'RON'))->format() : 'Preț la cerere' }}
            </span>

            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[3px] border border-line2 transition duration-300 group-hover:border-ink group-hover:bg-ink group-hover:text-light" aria-hidden="true">
                <x-storefront.icon name="arrow-right" class="h-4 w-4" />
            </span>
        </span>
    </div>
</a>
