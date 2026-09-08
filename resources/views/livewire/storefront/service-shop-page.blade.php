<div class="mx-auto max-w-3xl space-y-6">
    <x-seo :title="$shop->name"
           :description="$shop->description ?? ('Service auto în '.$shop->city.', '.$shop->county)"
           :canonical="route('storefront.service', $shop->slug)" />

    <nav class="text-xs text-stone-500">
        <a href="{{ route('storefront.services') }}" class="hover:underline">Service auto</a>
        <span class="mx-1">/</span>
        <span class="text-stone-900">{{ $shop->name }}</span>
    </nav>

    @php($tier = $shop->effectiveTier())

    <header class="space-y-2">
        <div class="flex flex-wrap items-baseline gap-3">
            <h1 class="text-3xl font-black tracking-tight">{{ $shop->name }}</h1>
            @if($tier->isPaid())
                <span class="rounded-full bg-lime-100 px-2 py-0.5 text-xs font-semibold text-lime-900">{{ $tier->label() }}</span>
            @endif
        </div>
        <p class="text-sm text-stone-600">{{ $shop->city }}, {{ $shop->county }}</p>
    </header>

    @if($tier->isPaid())
        {{-- Stated in words, not only as a badge: a reader should not have to work out that
             position was paid for. --}}
        <p class="rounded-lg border border-stone-200 bg-stone-50 p-3 text-xs text-stone-600">
            Acest service are o listare plătită în directorul nostru. Nu am verificat independent
            calitatea lucrărilor.
        </p>
    @endif

    @if($shop->description)
        <div class="prose prose-stone max-w-none">
            {!! app(\App\Support\HtmlSanitizer::class)->clean($shop->description) !!}
        </div>
    @endif

    <section class="rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="mb-3 text-lg font-bold">Contact</h2>
        <dl class="grid gap-y-2 text-sm sm:grid-cols-[8rem_1fr]">
            @if($shop->address)
                <dt class="text-stone-500">Adresă</dt>
                <dd>{{ $shop->address }}@if($shop->postal_code), {{ $shop->postal_code }}@endif</dd>
            @endif
            @if($shop->phone)
                <dt class="text-stone-500">Telefon</dt>
                <dd><a href="tel:{{ $shop->phone }}" class="underline">{{ $shop->phone }}</a></dd>
            @endif
            @if($shop->email)
                <dt class="text-stone-500">Email</dt>
                <dd><a href="mailto:{{ $shop->email }}" class="underline">{{ $shop->email }}</a></dd>
            @endif
            @if($shop->website)
                <dt class="text-stone-500">Website</dt>
                <dd><a href="{{ $shop->website }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">{{ $shop->website }}</a></dd>
            @endif
        </dl>
    </section>

    @if($shop->specialityList())
        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="mb-3 text-lg font-bold">Specializări</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($shop->specialityList() as $speciality)
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-sm">{{ $speciality }}</span>
                @endforeach
            </div>
        </section>
    @endif

    @if($shop->fits_parts_bought_here)
        <p class="rounded-xl border border-lime-300 bg-lime-50 p-4 text-sm text-lime-900">
            Acest service montează piesele cumpărate din magazinul nostru.
        </p>
    @endif
</div>
