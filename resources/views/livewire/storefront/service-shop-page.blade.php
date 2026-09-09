@php($tier = $shop->effectiveTier())
@php($openNow = $schedule->isOpenAt())

<div class="space-y-10">
    <x-seo :title="$shop->name.' · service auto în '.$shop->city"
           :description="$shop->description ? strip_tags($shop->description) : ('Service auto în '.$shop->city.', '.$shop->county.'. Program, servicii, prețuri și cerere de programare.')"
           :canonical="$shop->url()" />

    @push('meta')
        <script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
        <script type="application/ld+json">@json($breadcrumbs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endpush

    <nav class="flex flex-wrap items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}" class="hover:text-stone-900 hover:underline">Acasă</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('storefront.services') }}" class="hover:text-stone-900 hover:underline">Service auto</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('storefront.services.city', $shop->citySegment()) }}" class="hover:text-stone-900 hover:underline">{{ $shop->city }}</a>
        <span aria-hidden="true">/</span>
        <span class="text-stone-900">{{ $shop->name }}</span>
    </nav>

    {{-- Gallery first, because a workshop is judged on whether it looks like somewhere you would
         leave your car. --}}
    @if($shop->media->isNotEmpty())
        <div class="grid gap-2 sm:grid-cols-[2fr_1fr_1fr] sm:grid-rows-2">
            @foreach($shop->media->take(5) as $medium)
                <a href="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}" target="_blank" rel="noopener"
                   @class(['overflow-hidden rounded-xl bg-stone-100', 'sm:row-span-2' => $loop->first])>
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}"
                         alt="{{ $medium->alt_text ?: $shop->name }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                         class="{{ $loop->first ? 'aspect-4/3 sm:h-full' : 'aspect-4/3' }} w-full object-cover transition hover:scale-[1.02]">
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-10">
            <header class="space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl font-bold tracking-tight text-stone-900">{{ $shop->name }}</h1>

                    @if($openNow)
                        <span class="pill-positive">Deschis acum</span>
                    @elseif(! $schedule->isEmpty())
                        <span class="pill-neutral">Închis acum</span>
                    @endif

                    @if($tier->isPaid())
                        <span class="pill-warning">{{ $tier->label() }}</span>
                    @endif
                </div>

                <p class="text-sm text-stone-600">
                    {{ collect([$shop->address, $shop->postal_code, $shop->city, $shop->county])->filter()->implode(', ') }}
                </p>

                @if($shop->fits_parts_bought_here)
                    <p class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-900">
                        <x-storefront.icon name="check" class="h-4 w-4" />
                        Montează piesele cumpărate din magazinul nostru
                    </p>
                @endif

                @if($shop->certificationLabels())
                    <div class="flex flex-wrap gap-2">
                        @foreach($shop->certificationLabels() as $label)
                            <span class="rounded-full border border-stone-300 px-3 py-1 text-xs font-medium text-stone-700">{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
            </header>

            @if($tier->isPaid())
                {{-- In words, not only as a badge: a reader should not have to work out that
                     position in the directory was paid for. --}}
                <p class="rounded-lg border border-stone-200 bg-stone-50 p-3 text-xs text-stone-600">
                    Acest service are o listare plătită în directorul nostru, ceea ce îi influențează
                    poziția în listă. Nu am verificat independent calitatea lucrărilor.
                </p>
            @endif

            @if($shop->description)
                <section class="space-y-3">
                    <h2 class="text-lg font-bold tracking-tight">Despre service</h2>
                    <div class="prose prose-stone max-w-none">
                        {!! app(\App\Support\HtmlSanitizer::class)->clean($shop->description) !!}
                    </div>
                </section>
            @endif

            @if($servicesByCategory->isNotEmpty())
                <section class="space-y-4" id="servicii">
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <h2 class="text-lg font-bold tracking-tight">Servicii și prețuri</h2>
                        <p class="text-xs text-stone-500">Prețurile sunt orientative și confirmate de service la programare.</p>
                    </div>

                    @foreach($servicesByCategory as $categoryName => $services)
                        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
                            <h3 class="border-b border-stone-200 bg-stone-50 px-4 py-2.5 text-sm font-semibold text-stone-900">{{ $categoryName }}</h3>

                            <ul class="divide-y divide-stone-100">
                                @foreach($services as $service)
                                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-3">
                                        <div class="min-w-0">
                                            <a href="{{ route('storefront.service-type', $service->slug) }}" class="text-sm font-medium text-stone-900 hover:underline">
                                                {{ $service->name }}
                                            </a>

                                            {{-- The bridge to the catalogue: the job names the parts it consumes. --}}
                                            @if($service->partsCategory)
                                                <a href="{{ route('storefront.category', $service->partsCategory->full_path) }}"
                                                   class="ml-2 text-xs text-stone-500 hover:text-stone-900 hover:underline">
                                                    piese pentru această lucrare
                                                </a>
                                            @endif

                                            @if($service->pivot->note)
                                                <p class="text-xs text-stone-500">{{ $service->pivot->note }}</p>
                                            @endif
                                        </div>

                                        <div class="shrink-0 text-right text-sm">
                                            @if($service->pivot->price_from !== null)
                                                <span class="font-semibold tabular-nums text-stone-900">
                                                    @if($service->pivot->price_to !== null && $service->pivot->price_to != $service->pivot->price_from)
                                                        {{ \App\Support\Money::of($service->pivot->price_from, $service->pivot->currency)->format() }}
                                                        – {{ \App\Support\Money::of($service->pivot->price_to, $service->pivot->currency)->format() }}
                                                    @else
                                                        de la {{ \App\Support\Money::of($service->pivot->price_from, $service->pivot->currency)->format() }}
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-stone-500">preț la cerere</span>
                                            @endif

                                            @if($service->pivot->duration_minutes)
                                                <div class="text-xs text-stone-400">~{{ $service->pivot->duration_minutes }} min</div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </section>
            @endif

            @if($shop->makes->isNotEmpty())
                <section class="space-y-3">
                    <h2 class="text-lg font-bold tracking-tight">Mărci deservite</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($shop->makes as $make)
                            <span class="rounded-full bg-stone-100 px-3 py-1 text-sm text-stone-700">{{ $make->name }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($shop->specialityList())
                <section class="space-y-3">
                    <h2 class="text-lg font-bold tracking-tight">Specializări</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($shop->specialityList() as $speciality)
                            <span class="rounded-full bg-stone-100 px-3 py-1 text-sm text-stone-700">{{ $speciality }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($shop->amenityLabels() || $shop->paymentLabels())
                <section class="grid gap-8 sm:grid-cols-2">
                    @if($shop->amenityLabels())
                        <div class="space-y-3">
                            <h2 class="text-lg font-bold tracking-tight">Dotări</h2>
                            <ul class="space-y-1.5 text-sm text-stone-700">
                                @foreach($shop->amenityLabels() as $label)
                                    <li class="flex items-center gap-2">
                                        <x-storefront.icon name="check" class="h-4 w-4 shrink-0 text-emerald-600" /> {{ $label }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($shop->paymentLabels())
                        <div class="space-y-3">
                            <h2 class="text-lg font-bold tracking-tight">Metode de plată</h2>
                            <ul class="space-y-1.5 text-sm text-stone-700">
                                @foreach($shop->paymentLabels() as $label)
                                    <li class="flex items-center gap-2">
                                        <x-storefront.icon name="check" class="h-4 w-4 shrink-0 text-emerald-600" /> {{ $label }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endif

            @if($shop->latitude && $shop->longitude)
                {{-- Loaded on request rather than on page load: the map is a third-party frame,
                     and most visitors want the address, not the tiles. --}}
                <section class="space-y-3" x-data="{ loaded: false }">
                    <h2 class="text-lg font-bold tracking-tight">Unde se află</h2>

                    <div class="overflow-hidden rounded-xl border border-stone-200 bg-stone-100">
                        <template x-if="loaded">
                            <iframe title="Harta către {{ $shop->name }}" class="h-80 w-full" loading="lazy"
                                    src="https://www.openstreetmap.org/export/embed.html?bbox={{ $shop->longitude - 0.01 }},{{ $shop->latitude - 0.008 }},{{ $shop->longitude + 0.01 }},{{ $shop->latitude + 0.008 }}&layer=mapnik&marker={{ $shop->latitude }},{{ $shop->longitude }}"></iframe>
                        </template>

                        <div x-show="! loaded" class="flex h-40 flex-col items-center justify-center gap-3 text-sm text-stone-600">
                            <p>Harta este încărcată de la OpenStreetMap.</p>
                            <button type="button" @click="loaded = true" class="btn-secondary">Arată harta</button>
                        </div>
                    </div>
                </section>
            @endif

            @if($nearby->isNotEmpty())
                <section class="space-y-3">
                    <h2 class="text-lg font-bold tracking-tight">Alte service-uri din {{ $shop->city }}</h2>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($nearby as $other)
                            <a href="{{ $other->url() }}" class="rounded-xl border border-stone-200 bg-white p-4 transition hover:border-stone-900">
                                <span class="block font-semibold text-stone-900">{{ $other->name }}</span>
                                <span class="block text-sm text-stone-500">{{ $other->address ?: $other->city }}</span>
                                @if($other->fits_parts_bought_here)
                                    <span class="mt-2 inline-block text-xs font-semibold text-emerald-700">montează piesele noastre</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- The rail holds the two things a visitor came for: when it is open, and how to get in
             touch. It sticks because the services list above it is long. --}}
        <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <div class="space-y-3 rounded-xl border border-stone-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-stone-500">Contact</h2>

                @if($shop->phone)
                    @if($phoneVisible)
                        <a href="tel:{{ preg_replace('/\s+/', '', $shop->phone) }}" class="btn-primary w-full">
                            <x-storefront.icon name="phone" class="h-4 w-4" /> {{ $shop->phone }}
                        </a>
                    @else
                        <button type="button" wire:click="revealPhone" class="btn-primary w-full">
                            <x-storefront.icon name="phone" class="h-4 w-4" /> Arată telefonul
                        </button>
                    @endif
                @endif

                @if($shop->website)
                    <a href="{{ route('storefront.service.link', ['city' => $shop->citySegment(), 'slug' => $shop->slug, 'type' => 'website']) }}"
                       target="_blank" rel="noopener nofollow" class="btn-secondary w-full">Website</a>
                @endif

                <a href="{{ route('storefront.service.link', ['city' => $shop->citySegment(), 'slug' => $shop->slug, 'type' => 'directions']) }}"
                   target="_blank" rel="noopener nofollow" class="btn-secondary w-full">
                    <x-storefront.icon name="pin" class="h-4 w-4" /> Cum ajung
                </a>

                @if($shop->email)
                    <a href="mailto:{{ $shop->email }}" class="block text-center text-sm text-stone-600 hover:text-stone-900 hover:underline">{{ $shop->email }}</a>
                @endif
            </div>

            @unless($schedule->isEmpty())
                <div class="rounded-xl border border-stone-200 bg-white p-5">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-stone-500">Program</h2>

                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($schedule->week() as $row)
                                <tr @class(['font-semibold text-stone-900' => $row['weekday'] === (int) now()->isoWeekday()])>
                                    <td class="py-1 text-stone-600">{{ $row['label'] }}</td>
                                    <td class="py-1 text-right tabular-nums">
                                        {{ $row['closed'] ? 'închis' : $row['opens'].'–'.$row['closes'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endunless

            @if($shop->accepts_appointments)
                <div class="rounded-xl border border-stone-900 bg-white p-5" id="programare">
                    <h2 class="text-base font-bold tracking-tight text-stone-900">Cere o programare</h2>
                    <p class="mt-1 text-xs text-stone-500">
                        Trimiți o cerere, nu o rezervare confirmată. Service-ul te contactează pentru a stabili ora.
                    </p>

                    @if($linkedOrder)
                        <p class="mt-3 rounded-lg bg-stone-100 p-3 text-xs text-stone-700">
                            Cererea se leagă de comanda <strong>{{ $linkedOrder->number }}</strong>, ca service-ul să știe
                            ce piese urmează să monteze.
                        </p>
                    @endif

                    @if($formError)
                        <p class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-800">{{ $formError }}</p>
                    @endif

                    <form wire:submit="submit" class="mt-4 space-y-3">
                        @if($shop->services->isNotEmpty())
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-stone-600">Ce ai nevoie</span>
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
                                    <span class="mb-1 block text-xs font-medium text-stone-600">Mașina</span>
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
                                <span class="mb-1 block text-xs font-medium text-stone-600">Mașina</span>
                                <input wire:model="vehicleLabel" placeholder="Suzuki Jimny 2018">
                                @error('vehicleLabel') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-stone-600">Data preferată</span>
                                <input type="date" wire:model="preferredDate" min="{{ now()->toDateString() }}">
                                @error('preferredDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-stone-600">Intervalul</span>
                                <select wire:model="preferredSlot">
                                    @foreach($slots as $slot)
                                        <option value="{{ $slot->value }}">{{ $slot->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-stone-600">Numele tău</span>
                            <input wire:model="name">
                            @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-stone-600">Telefon</span>
                            <input type="tel" wire:model="phone">
                            @error('phone') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-stone-600">Email <span class="font-normal text-stone-400">(opțional)</span></span>
                            <input type="email" wire:model="email">
                            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-stone-600">Detalii</span>
                            <textarea wire:model="message" rows="3" placeholder="Ce ai observat la mașină, ce piese ai deja..."></textarea>
                            @error('message') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>

                        {{-- The request hands a name and a phone number to a third party, so the
                             agreement is asked for plainly instead of buried in a footer link. --}}
                        <label class="flex items-start gap-2 text-xs text-stone-600">
                            <input type="checkbox" wire:model="consent" class="mt-0.5">
                            <span>
                                Sunt de acord ca numele și datele mele de contact să fie transmise acestui service,
                                ca să mă poată contacta.
                            </span>
                        </label>
                        @error('consent') <span class="block text-xs text-red-600">{{ $message }}</span> @enderror

                        <button type="submit" class="btn-primary w-full">Trimite cererea</button>
                    </form>
                </div>
            @endif
        </aside>
    </div>
</div>
