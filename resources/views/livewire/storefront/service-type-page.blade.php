<div>
    <x-seo :title="$service->name.' — preț, durată și service-uri'"
           :description="$service->description ? strip_tags($service->description) : ('Ce presupune '.mb_strtolower($service->name).', cât durează, cât costă orientativ și unde se face.')"
           :canonical="route('storefront.service-type', $service->slug)" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pb-12 pt-14 sm:pt-20">
            <nav class="flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-[.1em] text-mute" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}" class="transition hover:text-bone">Acasă</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('storefront.service-types') }}" class="transition hover:text-bone">Lucrări</a>
                <span aria-hidden="true">/</span>
                <span class="text-bone">{{ $service->name }}</span>
            </nav>

            <h1 class="st-display mt-6 max-w-4xl text-[clamp(2.4rem,5.4vw,5rem)] leading-[.92]">{{ $service->name }}</h1>

            <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 font-mono text-[12px] uppercase tracking-[.08em] text-mute">
                @if($service->serviceCategory)
                    <span>{{ $service->serviceCategory->name }}</span>
                @endif
                @if($service->typical_duration_minutes)
                    <span>durează în medie ~{{ $service->typical_duration_minutes }} min</span>
                @endif
                <span>{{ $shops->total() }} service-uri o fac</span>
            </div>

            @if($service->description)
                <div class="mt-6 max-w-[64ch] text-[16px] leading-relaxed text-[#cfcdc6] [&_a]:underline [&_li]:ml-5 [&_p]:mb-3 [&_ul]:list-disc">
                    {!! app(\App\Support\HtmlSanitizer::class)->clean($service->description) !!}
                </div>
            @endif
        </div>
    </section>

    <div class="shell grid gap-16 pb-24 pt-12 sm:pt-16">
        @if($parts->isNotEmpty())
            {{-- The reason this taxonomy exists: someone reading about a job is one click from the
                 parts it needs. --}}
            <section class="grid gap-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="grid gap-2">
                        <h2 class="st-display text-[clamp(1.7rem,2.6vw,2.4rem)]">Piese pentru această lucrare</h2>
                        @php($vehicle = app(\App\Storefront\VehicleContext::class)->current())
                        <p class="text-sm text-ink2">
                            {{ $vehicle ? 'Filtrate pentru '.$vehicle->label().'.' : 'Nefiltrate — alege-ți mașina din header ca să vezi doar ce se potrivește.' }}
                        </p>
                    </div>
                    <a href="{{ route('storefront.category', $service->partsCategory->full_path) }}" class="st-link">
                        Vezi toate <x-storefront.icon name="arrow-right" />
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    @foreach($parts as $product)
                        @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => null])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="grid gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="st-display text-[clamp(1.7rem,2.6vw,2.4rem)]">Service-uri care fac această lucrare</h2>

                <label class="flex items-center gap-3 text-sm">
                    <span class="field-label mb-0">Oraș</span>
                    <select wire:model.live="city" class="w-52">
                        <option value="">Toate</option>
                        @foreach($cities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                </label>
            </div>

            @if($shops->isEmpty())
                <p class="rounded-[3px] border border-dashed border-line2 bg-white p-10 text-center text-ink2">
                    Niciun service listat pentru această lucrare deocamdată.
                </p>
            @else
                <p class="text-xs text-ink2">
                    Ordinea este influențată de listările plătite, marcate ca atare. Prețurile sunt
                    orientative și se confirmă de service la programare.
                </p>

                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($shops as $shop)
                        <x-storefront.shop-card :shop="$shop" />
                    @endforeach
                </div>

                <div>{{ $shops->links() }}</div>
            @endif
        </section>
    </div>
</div>
