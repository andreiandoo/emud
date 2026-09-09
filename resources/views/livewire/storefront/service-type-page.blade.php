<div class="space-y-10">
    <x-seo :title="$service->name.' — preț, durată și service-uri'"
           :description="$service->description ? strip_tags($service->description) : ('Ce presupune '.mb_strtolower($service->name).', cât durează, cât costă orientativ și unde se face.')"
           :canonical="route('storefront.service-type', $service->slug)" />

    <nav class="flex flex-wrap items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}" class="hover:text-stone-900 hover:underline">Acasă</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('storefront.service-types') }}" class="hover:text-stone-900 hover:underline">Lucrări</a>
        <span aria-hidden="true">/</span>
        <span class="text-stone-900">{{ $service->name }}</span>
    </nav>

    <header class="space-y-3">
        <h1 class="text-3xl font-bold tracking-tight">{{ $service->name }}</h1>

        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-stone-600">
            @if($service->serviceCategory)
                <span>{{ $service->serviceCategory->name }}</span>
            @endif
            @if($service->typical_duration_minutes)
                <span>durează în medie ~{{ $service->typical_duration_minutes }} min</span>
            @endif
            <span>{{ $shops->total() }} service-uri o fac</span>
        </div>

        @if($service->description)
            <div class="prose prose-stone max-w-none">
                {!! app(\App\Support\HtmlSanitizer::class)->clean($service->description) !!}
            </div>
        @endif
    </header>

    @if($parts->isNotEmpty())
        {{-- The reason this taxonomy exists: someone reading about a job is one click from the
             parts it needs. --}}
        <section class="space-y-3">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-lg font-bold tracking-tight">Piese pentru această lucrare</h2>
                <a href="{{ route('storefront.category', $service->partsCategory->full_path) }}" class="text-sm font-semibold underline underline-offset-4">
                    Vezi toate
                </a>
            </div>

            @php($vehicle = app(\App\Storefront\VehicleContext::class)->current())
            <p class="text-xs text-stone-500">
                {{ $vehicle ? 'Filtrate pentru '.$vehicle->label().'.' : 'Nefiltrate — alege-ți mașina din header ca să vezi doar ce se potrivește.' }}
            </p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($parts as $product)
                    <a href="{{ route('storefront.product', $product->slug) }}" class="rounded-xl border border-stone-200 bg-white p-4 transition hover:border-stone-900">
                        <span class="block truncate text-sm font-medium text-stone-900">{{ $product->name }}</span>
                        <span class="block text-xs text-stone-500">{{ $product->brand?->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="text-lg font-bold tracking-tight">Service-uri care fac această lucrare</h2>

            <label class="flex items-center gap-2 text-sm">
                <span class="text-stone-600">Oraș</span>
                <select wire:model.live="city" class="w-48">
                    <option value="">Toate</option>
                    @foreach($cities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </select>
            </label>
        </div>

        @if($shops->isEmpty())
            <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
                Niciun service listat pentru această lucrare deocamdată.
            </p>
        @else
            <p class="text-xs text-stone-500">
                Ordinea este influențată de listările plătite, marcate ca atare. Prețurile sunt
                orientative și se confirmă de service la programare.
            </p>

            <div class="space-y-3">
                @foreach($shops as $shop)
                    <x-storefront.shop-card :shop="$shop" />
                @endforeach
            </div>

            <div>{{ $shops->links() }}</div>
        @endif
    </section>
</div>
