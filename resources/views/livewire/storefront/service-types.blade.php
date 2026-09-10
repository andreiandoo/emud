<div>
    <x-seo title="Lucrări și servicii auto"
           description="Ce înseamnă fiecare lucrare la mașină, cât durează, cât costă orientativ și ce piese folosește." />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pb-14 pt-16 sm:pt-24">
            <p class="st-kicker text-mute">Service auto</p>
            <h1 class="st-display mt-5 max-w-[14ch] text-[clamp(2.6rem,6vw,5.75rem)] leading-[.92]">Lucrări și servicii auto</h1>
            <p class="mt-5 max-w-[56ch] text-[clamp(1rem,1.2vw,1.15rem)] text-[#cfcdc6]">
                Ce presupune fiecare lucrare, cine o face și ce piese cere.
            </p>
            <a href="{{ route('storefront.services') }}" class="st-btn mt-8">Găsește un service <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
        </div>
    </section>

    <div class="shell grid gap-14 pb-24 pt-12 sm:pt-16">
        @forelse($categories as $category)
            @continue($category->services->isEmpty())

            <section class="grid gap-5">
                <h2 class="flex items-center gap-3 font-display text-[clamp(1.5rem,2.2vw,2rem)] font-semibold">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-g0 text-sand">
                        <x-storefront.icon :name="$category->icon ?: 'tool'" class="h-5 w-5" />
                    </span>
                    {{ $category->name }}
                </h2>

                <div class="grid gap-px overflow-hidden rounded-[3px] border border-line bg-line sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($category->services as $service)
                        <a href="{{ route('storefront.service-type', $service->slug) }}"
                           class="group flex items-center justify-between gap-4 bg-white p-5 transition hover:bg-light">
                            <span class="min-w-0">
                                <span class="block font-medium text-ink">{{ $service->name }}</span>
                                <span class="mt-1 block font-mono text-[11px] uppercase tracking-[.06em] text-ink2">
                                    {{ $service->shops_count }} {{ $service->shops_count === 1 ? 'service' : 'service-uri' }}
                                    @if($service->typical_duration_minutes) · ~{{ $service->typical_duration_minutes }} min @endif
                                </span>
                            </span>
                            <x-storefront.icon name="arrow-right" class="h-4 w-4 shrink-0 text-ink2 transition-transform duration-500 group-hover:translate-x-1 group-hover:text-ink" />
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded-[3px] border border-dashed border-line2 bg-white p-10 text-center text-ink2">
                Nu am publicat încă lista de lucrări.
            </p>
        @endforelse
    </div>
</div>
