@php($embed = \App\Support\VideoEmbed::url($collection->video_url))
@php($years = $collection->yearRange())

<div class="space-y-10">
    <x-seo :title="$collection->seo_title ?: $collection->name"
           :description="$collection->seo_description ?: $collection->subtitle"
           :canonical="$collection->url()"
           :image="$collection->ogImageUrl()"
           :index="$collection->robots_index"
           :follow="$collection->robots_follow" />

    @push('meta')
        <script type="application/ld+json">{!! $breadcrumbs !!}</script>
    @endpush

    <nav class="text-xs text-stone-500">
        <a href="{{ route('storefront.home') }}" class="hover:underline">Acasă</a>
        <span class="mx-1">/</span>
        <a href="{{ route('storefront.collections') }}" class="hover:underline">Colecții</a>
        <span class="mx-1">/</span>
        <span class="text-stone-900">{{ $collection->name }}</span>
    </nav>

    {{-- The wide image is the header rather than an illustration inside it: a customer landing
         here from a search should recognise their own car before reading a word. --}}
    <section class="relative overflow-hidden rounded-3xl bg-stone-900 text-white">
        @if($collection->wideImageUrl())
            <img src="{{ $collection->wideImageUrl() }}" alt="{{ $collection->name }}"
                 class="absolute inset-0 h-full w-full object-cover opacity-70">
        @endif
        <div class="relative bg-gradient-to-r from-stone-950/85 via-stone-950/60 to-transparent px-6 py-14 sm:px-12 sm:py-20">
            <p class="text-xs font-semibold uppercase tracking-[.2em] text-stone-300">Colecție</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-5xl">{{ $collection->name }}</h1>
            @if($years)
                <p class="mt-1 text-sm text-stone-300">{{ $years }}</p>
            @endif
            @if($collection->subtitle)
                <p class="mt-3 max-w-xl text-stone-200">{{ $collection->subtitle }}</p>
            @endif
            <p class="mt-5 text-sm text-stone-300">
                {{ $products->total() }} {{ $products->total() === 1 ? 'produs' : 'produse' }} în colecție
            </p>
        </div>
    </section>

    @if($embed)
        <section class="overflow-hidden rounded-2xl bg-stone-950">
            <div class="aspect-video">
                <iframe src="{{ $embed }}" title="{{ $collection->name }}" loading="lazy"
                        class="h-full w-full" frameborder="0" allowfullscreen
                        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
        </section>
    @elseif($collection->video_url)
        <a href="{{ $collection->video_url }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 text-sm font-semibold underline underline-offset-4">
            Vezi materialul video
        </a>
    @endif

    @if($collection->description)
        <section class="prose prose-stone max-w-3xl">
            {!! nl2br(e($collection->description)) !!}
        </section>
    @endif

    <section class="space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="mr-auto text-xl font-bold">Produse pentru {{ $collection->name }}</h2>

            @if($brands->isNotEmpty())
                <select wire:model.live="brand" class="w-auto text-sm" aria-label="Brand">
                    <option value="">Toate brandurile</option>
                    @foreach($brands as $availableBrand)
                        <option value="{{ $availableBrand->slug }}">{{ $availableBrand->name }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="sort" class="w-auto text-sm" aria-label="Ordonare">
                <option value="relevance">Recomandate</option>
                <option value="name">Nume A–Z</option>
                <option value="newest">Cele mai noi</option>
            </select>
        </div>

        @if($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="$set('category', '')"
                        class="rounded-full px-3 py-1 text-sm font-semibold transition {{ $category === '' ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' }}">
                    Toate
                </button>
                @foreach($categories as $availableCategory)
                    <button type="button" wire:click="$set('category', '{{ $availableCategory->slug }}')"
                            class="rounded-full px-3 py-1 text-sm font-semibold transition {{ $category === $availableCategory->slug ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200' }}">
                        {{ $availableCategory->name }}
                    </button>
                @endforeach
            </div>
        @endif

        @if($products->isEmpty())
            <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
                Nu avem încă produse listate pentru {{ $collection->name }}.
                <a href="{{ route('storefront.contact') }}" class="font-semibold underline">Scrie-ne ce cauți</a> și îți spunem dacă putem aduce.
            </p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($products as $product)
                    @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => $verdicts($product)])
                @endforeach
            </div>

            {{ $products->links() }}
        @endif
    </section>

    @if($reviews->isNotEmpty())
        <section class="space-y-5">
            <h2 class="text-xl font-bold">Ce spun cei care conduc {{ $collection->name }}</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($reviews as $review)
                    <x-storefront.review-card :review="$review" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-2xl bg-stone-100 p-6">
        <h2 class="text-lg font-bold">Ai un {{ $collection->name }}?</h2>
        <p class="mt-1 text-sm text-stone-600">
            Salvează-l în garaj și îți arătăm doar ce se potrivește pe el, de fiecare dată când intri.
        </p>
        <a href="{{ route('customer.garage') }}" class="mt-4 inline-flex rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">
            Adaugă în garaj
        </a>
    </section>
</div>
