@php($tier = $shop->effectiveTier())
@php($openNow = $schedule->isOpenAt())

<div>
    <x-seo :title="$shop->name.' · service auto în '.$shop->city"
           :description="$shop->description ? strip_tags($shop->description) : ('Service auto în '.$shop->city.', '.$shop->county.'. Program, servicii, prețuri și cerere de programare.')"
           :canonical="$shop->url()" />

    @push('meta')
        <script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
        <script type="application/ld+json">@json($breadcrumbs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endpush

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pb-12 pt-14 sm:pt-20">
            <nav class="flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-[.1em] text-mute" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}" class="transition hover:text-bone">Acasă</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('storefront.services') }}" class="transition hover:text-bone">Service auto</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('storefront.services.city', $shop->citySegment()) }}" class="transition hover:text-bone">{{ $shop->city }}</a>
                <span aria-hidden="true">/</span>
                <span class="text-bone">{{ $shop->name }}</span>
            </nav>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if($openNow)
                    <span class="inline-flex items-center gap-2 rounded-full bg-fit-bright/15 px-3 py-1 text-xs font-semibold text-fit-bright"><span class="h-2 w-2 rounded-full bg-fit-bright"></span> Deschis acum</span>
                @elseif(! $schedule->isEmpty())
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-mute"><span class="h-2 w-2 rounded-full bg-mute"></span> Închis acum</span>
                @endif

                @if($tier->isPaid())
                    <span class="pill-warning">{{ $tier->label() }}</span>
                @endif
            </div>

            <h1 class="st-display mt-4 max-w-4xl text-[clamp(2.4rem,5.4vw,5rem)] leading-[.92]">{{ $shop->name }}</h1>

            <p class="mt-4 flex items-start gap-2 text-[#cfcdc6]">
                <x-storefront.icon name="pin" class="mt-1 h-4 w-4 shrink-0 text-sand" />
                {{ collect([$shop->address, $shop->postal_code, $shop->city, $shop->county])->filter()->implode(', ') }}
            </p>

            <div class="mt-6 flex flex-wrap gap-2">
                @if($shop->fits_parts_bought_here)
                    <span class="inline-flex items-center gap-2 rounded-full bg-sand px-3.5 py-1.5 text-sm font-semibold text-sandink">
                        <x-storefront.icon name="wrench" class="h-4 w-4" />
                        Montează piesele cumpărate din magazinul nostru
                    </span>
                @endif

                @foreach($shop->certificationLabels() as $label)
                    <span class="rounded-full border border-white/20 px-3.5 py-1.5 text-xs font-medium text-[#d8d6cf]">{{ $label }}</span>
                @endforeach
            </div>
        </div>
    </section>

    <div class="shell pb-24 pt-10 sm:pt-12">
        {{-- Gallery first, because a workshop is judged on whether it looks like somewhere you would
             leave your car. --}}
        @if($shop->media->isNotEmpty())
            <div class="mb-12 grid gap-2 sm:grid-cols-[2fr_1fr_1fr] sm:grid-rows-2">
                @foreach($shop->media->take(5) as $medium)
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}" target="_blank" rel="noopener"
                       @class(['st-tile block bg-line', 'sm:row-span-2' => $loop->first])>
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}"
                             alt="{{ $medium->alt_text ?: $shop->name }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                             class="{{ $loop->first ? 'aspect-4/3 sm:h-full' : 'aspect-4/3' }} w-full object-cover transition duration-[1200ms] ease-[cubic-bezier(.2,.8,.2,1)] hover:scale-[1.04]">
                    </a>
                @endforeach
            </div>
        @endif

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_23rem]">
            <div class="grid min-w-0 content-start gap-12">
                @if($tier->isPaid())
                    {{-- In words, not only as a badge: a reader should not have to work out that
                         position in the directory was paid for. --}}
                    <p class="rounded-[3px] border border-line bg-white p-4 text-xs text-ink2">
                        Acest service are o listare plătită în directorul nostru, ceea ce îi influențează
                        poziția în listă. Nu am verificat independent calitatea lucrărilor.
                    </p>
                @endif

                @if($shop->description)
                    <section class="grid gap-4">
                        <h2 class="st-display text-[clamp(1.7rem,2.6vw,2.4rem)]">Despre service</h2>
                        <div class="max-w-[68ch] text-[16px] leading-relaxed text-ink2 [&_a]:underline [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-4 [&_strong]:text-ink [&_ul]:mb-4 [&_ul]:list-disc">
                            {!! app(\App\Support\HtmlSanitizer::class)->clean($shop->description) !!}
                        </div>
                    </section>
                @endif

                @if($servicesByCategory->isNotEmpty())
                    <section class="grid gap-5" id="servicii">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <h2 class="st-display text-[clamp(1.7rem,2.6vw,2.4rem)]">Servicii și prețuri</h2>
                            <p class="text-xs text-ink2">Prețurile sunt orientative și confirmate de service la programare.</p>
                        </div>

                        @foreach($servicesByCategory as $categoryName => $services)
                            <div class="overflow-hidden rounded-[3px] border border-line bg-white">
                                <h3 class="border-b border-line bg-light px-5 py-3 font-mono text-[11px] uppercase tracking-[.1em] text-ink2">{{ $categoryName }}</h3>

                                <ul class="divide-y divide-line">
                                    @foreach($services as $service)
                                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-3.5">
                                            <div class="min-w-0">
                                                <a href="{{ route('storefront.service-type', $service->slug) }}" class="font-medium text-ink hover:underline">
                                                    {{ $service->name }}
                                                </a>

                                                {{-- The bridge to the catalogue: the job names the parts it consumes. --}}
                                                @if($service->partsCategory)
                                                    <a href="{{ route('storefront.category', $service->partsCategory->full_path) }}"
                                                       class="ml-2 whitespace-nowrap text-xs text-ink2 underline-offset-2 hover:text-signal hover:underline">
                                                        piese pentru această lucrare
                                                    </a>
                                                @endif

                                                @if($service->pivot->note)
                                                    <p class="text-xs text-ink2">{{ $service->pivot->note }}</p>
                                                @endif
                                            </div>

                                            <div class="shrink-0 text-right text-sm">
                                                @if($service->pivot->price_from !== null)
                                                    <span class="font-display text-[15px] font-semibold tabular-nums text-ink">
                                                        @if($service->pivot->price_to !== null && $service->pivot->price_to != $service->pivot->price_from)
                                                            {{ \App\Support\Money::of($service->pivot->price_from, $service->pivot->currency)->format() }}
                                                            – {{ \App\Support\Money::of($service->pivot->price_to, $service->pivot->currency)->format() }}
                                                        @else
                                                            de la {{ \App\Support\Money::of($service->pivot->price_from, $service->pivot->currency)->format() }}
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="text-ink2">preț la cerere</span>
                                                @endif

                                                @if($service->pivot->duration_minutes)
                                                    <div class="font-mono text-[11px] text-ink2">~{{ $service->pivot->duration_minutes }} min</div>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </section>
                @endif

                @if($shop->makes->isNotEmpty() || $shop->specialityList())
                    <section class="grid gap-8 sm:grid-cols-2">
                        @if($shop->makes->isNotEmpty())
                            <div class="grid content-start gap-3">
                                <h2 class="font-display text-xl font-semibold">Mărci deservite</h2>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($shop->makes as $make)
                                        <span class="rounded-full bg-white px-3 py-1.5 text-sm">{{ $make->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($shop->specialityList())
                            <div class="grid content-start gap-3">
                                <h2 class="font-display text-xl font-semibold">Specializări</h2>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($shop->specialityList() as $speciality)
                                        <span class="rounded-full bg-white px-3 py-1.5 text-sm">{{ $speciality }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </section>
                @endif

                @if($shop->amenityLabels() || $shop->paymentLabels())
                    <section class="grid gap-8 sm:grid-cols-2">
                        @foreach([['Dotări', $shop->amenityLabels()], ['Metode de plată', $shop->paymentLabels()]] as [$heading, $labels])
                            @if($labels)
                                <div class="grid content-start gap-3">
                                    <h2 class="font-display text-xl font-semibold">{{ $heading }}</h2>
                                    <ul class="grid gap-2 text-sm">
                                        @foreach($labels as $label)
                                            <li class="flex items-center gap-2.5">
                                                <x-storefront.icon name="check" class="h-4 w-4 shrink-0 text-fit" /> {{ $label }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </section>
                @endif

                @if($shop->latitude && $shop->longitude)
                    {{-- Loaded on request rather than on page load: the map is a third-party frame,
                         and most visitors want the address, not the tiles. --}}
                    <section class="grid gap-4" x-data="{ loaded: false }">
                        <h2 class="font-display text-xl font-semibold">Unde se află</h2>

                        <div class="overflow-hidden rounded-[3px] border border-line bg-white">
                            <template x-if="loaded">
                                <iframe title="Harta către {{ $shop->name }}" class="h-80 w-full" loading="lazy"
                                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $shop->longitude - 0.01 }},{{ $shop->latitude - 0.008 }},{{ $shop->longitude + 0.01 }},{{ $shop->latitude + 0.008 }}&layer=mapnik&marker={{ $shop->latitude }},{{ $shop->longitude }}"></iframe>
                            </template>

                            <div x-show="! loaded" class="relative isolate grid h-48 place-content-center justify-items-center gap-3 overflow-hidden bg-g0 text-sm text-mute">
                                <canvas data-st-topo="rgba(241,238,230,.1)" class="absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>
                                <p>Harta este încărcată de la OpenStreetMap.</p>
                                <button type="button" @click="loaded = true" class="st-btn st-btn--ghost st-btn--sm">Arată harta</button>
                            </div>
                        </div>
                    </section>
                @endif

                @if($nearby->isNotEmpty())
                    <section class="grid gap-4">
                        <h2 class="font-display text-xl font-semibold">Alte service-uri din {{ $shop->city }}</h2>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($nearby as $other)
                                <a href="{{ $other->url() }}" class="group grid gap-1 rounded-[3px] border border-line bg-white p-4 transition hover:border-ink">
                                    <span class="font-display text-lg font-semibold">{{ $other->name }}</span>
                                    <span class="text-sm text-ink2">{{ $other->address ?: $other->city }}</span>
                                    @if($other->fits_parts_bought_here)
                                        <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-ink"><x-storefront.icon name="wrench" class="h-3.5 w-3.5 text-signal" /> montează piesele noastre</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- The rail holds the two things a visitor came for: when it is open, and how to get in
                 touch. It sticks because the services list beside it is long. --}}
            <aside class="grid content-start gap-4 lg:sticky lg:top-[calc(var(--st-header-h)+1.5rem)] lg:self-start">
                <div class="grid gap-3 rounded-[3px] bg-g0 p-5 text-bone">
                    <h2 class="st-kicker text-mute">Contact</h2>

                    @if($shop->phone)
                        @if($phoneVisible)
                            <a href="tel:{{ preg_replace('/\s+/', '', $shop->phone) }}" class="st-btn st-btn--block">
                                <x-storefront.icon name="phone" /> {{ $shop->phone }}
                            </a>
                        @else
                            <button type="button" wire:click="revealPhone" class="st-btn st-btn--block">
                                <x-storefront.icon name="phone" /> Arată telefonul
                            </button>
                        @endif
                    @endif

                    <div class="grid grid-cols-2 gap-2">
                        @if($shop->website)
                            <a href="{{ route('storefront.service.link', ['city' => $shop->citySegment(), 'slug' => $shop->slug, 'type' => 'website']) }}"
                               target="_blank" rel="noopener nofollow" class="st-btn st-btn--ghost st-btn--sm">Website</a>
                        @endif

                        <a href="{{ route('storefront.service.link', ['city' => $shop->citySegment(), 'slug' => $shop->slug, 'type' => 'directions']) }}"
                           target="_blank" rel="noopener nofollow" @class(['st-btn st-btn--ghost st-btn--sm', 'col-span-2' => ! $shop->website])>
                            <x-storefront.icon name="pin" /> Cum ajung
                        </a>
                    </div>

                    @if($shop->email)
                        <a href="mailto:{{ $shop->email }}" class="text-center text-sm text-mute transition hover:text-bone">{{ $shop->email }}</a>
                    @endif
                </div>

                @unless($schedule->isEmpty())
                    <div class="rounded-[3px] bg-white p-5">
                        <h2 class="st-kicker mb-3 text-ink2">Program</h2>

                        <table class="w-full text-sm">
                            <tbody>
                                @foreach($schedule->week() as $row)
                                    <tr @class(['font-semibold text-ink' => $row['weekday'] === (int) now()->isoWeekday()])>
                                        <td class="border-b border-line py-1.5 text-ink2">{{ $row['label'] }}</td>
                                        <td class="border-b border-line py-1.5 text-right font-mono tabular-nums">
                                            {{ $row['closed'] ? 'închis' : $row['opens'].'–'.$row['closes'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endunless

                @if($shop->accepts_appointments)
                    <div class="scroll-mt-32 rounded-[3px] border border-ink bg-white p-5" id="programare">
                        <p class="st-kicker text-ink2">Programare</p>
                        <h2 class="mt-2 font-display text-2xl font-semibold">Cere o programare</h2>
                        <p class="mt-1 text-xs text-ink2">
                            Trimiți o cerere, nu o rezervare confirmată. Service-ul te contactează pentru a stabili ora.
                        </p>

                        @if($linkedOrder)
                            <p class="mt-4 rounded-[3px] bg-sand p-3 text-xs text-sandink">
                                Cererea se leagă de comanda <strong>{{ $linkedOrder->number }}</strong>, ca service-ul să știe
                                ce piese urmează să monteze.
                            </p>
                        @endif

                        @if($formError)
                            <p class="mt-4 rounded-[3px] border border-red-300 bg-red-50 p-3 text-xs text-red-800">{{ $formError }}</p>
                        @endif

                        <form wire:submit="submit" class="mt-5 grid gap-3.5">
                            @if($shop->services->isNotEmpty())
                                <label class="block">
                                    <span class="field-label">Ce ai nevoie</span>
                                    <select wire:model="serviceId">
                                        <option value="">Nu știu încă / altceva</option>
                                        @foreach($shop->services as $service)
                                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            @auth
                                @if($garageVehicles->isNotEmpty())
                                    <label class="block">
                                        <span class="field-label">Mașina</span>
                                        <select wire:model.live="customerVehicleId">
                                            <option value="">Altă mașină</option>
                                            @foreach($garageVehicles as $vehicle)
                                                <option value="{{ $vehicle->id }}">{{ $vehicle->make?->name }} {{ $vehicle->model?->name }} · {{ $vehicle->year }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            @endauth

                            @if($customerVehicleId === null)
                                <label class="block">
                                    <span class="field-label">Mașina</span>
                                    <input wire:model="vehicleLabel" placeholder="Suzuki Jimny 2018">
                                    @error('vehicleLabel') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                            @endif

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="field-label">Data preferată</span>
                                    <input type="date" wire:model="preferredDate" min="{{ now()->toDateString() }}">
                                    @error('preferredDate') <span class="field-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="field-label">Intervalul</span>
                                    <select wire:model="preferredSlot">
                                        @foreach($slots as $slot)
                                            <option value="{{ $slot->value }}">{{ $slot->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            <label class="block">
                                <span class="field-label">Numele tău</span>
                                <input wire:model="name" autocomplete="name">
                                @error('name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>

                            <label class="block">
                                <span class="field-label">Telefon</span>
                                <input type="tel" wire:model="phone" autocomplete="tel">
                                @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                            </label>

                            <label class="block">
                                <span class="field-label">Email <span class="font-normal opacity-70">(opțional)</span></span>
                                <input type="email" wire:model="email" autocomplete="email">
                                @error('email') <span class="field-error">{{ $message }}</span> @enderror
                            </label>

                            <label class="block">
                                <span class="field-label">Detalii</span>
                                <textarea wire:model="message" rows="3" placeholder="Ce ai observat la mașină, ce piese ai deja..."></textarea>
                                @error('message') <span class="field-error">{{ $message }}</span> @enderror
                            </label>

                            {{-- The request hands a name and a phone number to a third party, so the
                                 agreement is asked for plainly instead of buried in a footer link. --}}
                            <label class="flex items-start gap-2.5 text-xs text-ink2">
                                <input type="checkbox" wire:model="consent" class="mt-0.5">
                                <span>
                                    Sunt de acord ca numele și datele mele de contact să fie transmise acestui service,
                                    ca să mă poată contacta.
                                </span>
                            </label>
                            @error('consent') <span class="field-error">{{ $message }}</span> @enderror

                            <button type="submit" class="st-btn st-btn--block">
                                <span wire:loading.remove wire:target="submit">Trimite cererea</span>
                                <span wire:loading wire:target="submit">Se trimite…</span>
                            </button>
                        </form>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</div>
