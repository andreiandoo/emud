<div x-data="{ tab: 'general' }">
    <x-admin.page-header :title="$shopId ? $name : 'Service nou'"
                         subtitle="Fișa publică a atelierului: contact, program, lucrări, galerie și promovare.">
        <x-slot:actions>
            <a href="{{ route('admin.service-shops.index') }}" class="btn-secondary">← Toate service-urile</a>
            @if($shopId && $shopStatus === 'published' && $shop)
                <a href="{{ $shop->url() }}" target="_blank" rel="noopener" class="btn-secondary">Vezi public</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if($saved)
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
    @endif

    @if($errors->any())
        <p class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            Verifică toate câmpurile marcate. {{ $errors->first() }}
        </p>
    @endif

    {{-- Alpine rather than Livewire tabs: switching a tab must not lose what has been typed into
         the other ones, and every field here belongs to a single unsaved listing. --}}
    <div class="mb-6 flex flex-wrap items-center gap-1">
        @foreach($tabs as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'bg-stone-100 font-semibold text-stone-900' : 'text-stone-500 hover:bg-stone-50 hover:text-stone-900'"
                    class="rounded-lg px-3 py-1.5 text-sm transition">{{ $label }}</button>
        @endforeach
    </div>

    <form wire:submit="save" class="card-padded space-y-5">
        <section x-show="tab === 'general'" class="grid gap-4 lg:grid-cols-2">
            <label class="block">
                <span class="field-label">Denumire</span>
                <input wire:model.live.debounce.500ms="name">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Slug</span>
                <input wire:model="slug">
                <span class="field-hint">Intră în URL-ul public; schimbarea lui rupe linkurile existente.</span>
                @error('slug') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block lg:col-span-2">
                <span class="field-label">Descriere (HTML)</span>
                <textarea wire:model="description" rows="8" class="font-mono text-xs"></textarea>
                <span class="field-hint">Filtrată la afișare printr-o listă de etichete permise.</span>
            </label>

            <label class="block">
                <span class="field-label">Specializări</span>
                <input wire:model="specialities" placeholder="off-road, suspensie, diagnoză">
                <span class="field-hint">Separate prin virgulă. Devin filtre în director.</span>
            </label>

            <label class="block">
                <span class="field-label">Stare</span>
                <select wire:model="shopStatus">
                    <option value="draft">Ciornă</option>
                    <option value="published">Publicat</option>
                </select>
            </label>

            <div class="flex flex-wrap gap-5 text-sm text-stone-700 lg:col-span-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="fitsPartsBoughtHere"> Montează piese cumpărate din magazinul nostru
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="acceptsAppointments"> Preia cereri de programare
                </label>
            </div>
        </section>

        <section x-show="tab === 'location'" x-cloak class="grid gap-4 lg:grid-cols-2">
            <label class="block">
                <span class="field-label">Județ</span>
                <input wire:model="county">
                @error('county') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Oraș</span>
                <input wire:model.live.debounce.500ms="city">
                @error('city') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Slug oraș</span>
                <input wire:model="citySlug">
                <span class="field-hint">Segmentul de oraș din URL-ul public al fișei.</span>
                @error('citySlug') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Cod poștal</span>
                <input wire:model="postalCode">
            </label>

            <label class="block lg:col-span-2">
                <span class="field-label">Adresă</span>
                <input wire:model="address">
            </label>

            <label class="block">
                <span class="field-label">Latitudine</span>
                <input wire:model="latitude" placeholder="46.7712">
                @error('latitude') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Longitudine</span>
                <input wire:model="longitude" placeholder="23.6236">
                <span class="field-hint">Fără coordonate, harta nu se afișează, iar „cum ajung” folosește adresa scrisă.</span>
                @error('longitude') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Telefon</span>
                <input wire:model="phone">
            </label>

            <label class="block">
                <span class="field-label">Email</span>
                <input type="email" wire:model="email">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block lg:col-span-2">
                <span class="field-label">Website</span>
                <input type="url" wire:model="website" placeholder="https://…">
                @error('website') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </section>

        <section x-show="tab === 'hours'" x-cloak class="space-y-2">
            <p class="text-sm text-stone-500">
                O zi bifată „închis” nu păstrează ore. O oră de închidere mai mică decât cea de
                deschidere înseamnă tură peste miezul nopții.
            </p>

            @foreach($hours as $index => $row)
                <div class="grid items-center gap-3 rounded-lg bg-stone-50 px-4 py-2.5 sm:grid-cols-[8rem_1fr_1fr_7rem]"
                     wire:key="hour-{{ $row['weekday'] }}">
                    <span class="text-sm font-medium text-stone-900">
                        {{ [1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi', 5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică'][$row['weekday']] }}
                    </span>

                    <input type="time" wire:model="hours.{{ $index }}.opens_at" @disabled($hours[$index]['is_closed'])>
                    <input type="time" wire:model="hours.{{ $index }}.closes_at" @disabled($hours[$index]['is_closed'])>

                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input type="checkbox" wire:model.live="hours.{{ $index }}.is_closed"> Închis
                    </label>

                    @error('hours.'.$index.'.opens_at') <span class="field-error sm:col-span-4">{{ $message }}</span> @enderror
                    @error('hours.'.$index.'.closes_at') <span class="field-error sm:col-span-4">{{ $message }}</span> @enderror
                </div>
            @endforeach
        </section>

        <section x-show="tab === 'services'" x-cloak class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-stone-500">
                    Prețurile sunt orientative și apar pe fișa publică marcate ca atare.
                </p>
                <button type="button" wire:click="addService" class="btn-secondary">+ Lucrare</button>
            </div>

            @if($services->isEmpty())
                <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    Nu există încă lucrări definite.
                    <a href="{{ route('admin.service-catalog') }}" class="font-semibold underline">Adaugă-le în catalogul de lucrări</a>.
                </p>
            @endif

            @foreach($priceList as $index => $row)
                <div class="grid items-end gap-4 rounded-xl bg-stone-50 p-4 md:grid-cols-[2fr_1fr_1fr_1fr_auto]" wire:key="price-{{ $index }}">
                    <label class="block">
                        <span class="field-label">Lucrare</span>
                        <select wire:model="priceList.{{ $index }}.service_id">
                            <option value="">Alege lucrarea</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}">{{ $service->serviceCategory?->name }} · {{ $service->name }}</option>
                            @endforeach
                        </select>
                        @error('priceList.'.$index.'.service_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Preț de la</span>
                        <x-admin.money-input wire:model="priceList.{{ $index }}.price_from" />
                        @error('priceList.'.$index.'.price_from') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Până la</span>
                        <x-admin.money-input wire:model="priceList.{{ $index }}.price_to" />
                        @error('priceList.'.$index.'.price_to') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Durată (min)</span>
                        <input type="number" min="5" wire:model="priceList.{{ $index }}.duration_minutes">
                        @error('priceList.'.$index.'.duration_minutes') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <button type="button" wire:click="removeService({{ $index }})" class="btn-danger">Elimină</button>

                    <label class="block md:col-span-5">
                        <span class="field-label">Notă</span>
                        <input wire:model="priceList.{{ $index }}.note" placeholder="fără materiale, doar manoperă…">
                    </label>
                </div>
            @endforeach
        </section>

        <section x-show="tab === 'gallery'" x-cloak class="space-y-4">
            <label class="block">
                <span class="field-label">Adaugă imagini</span>
                <input type="file" multiple accept="image/*" wire:model="newImages">
                <span class="field-hint">Prima imagine este cea folosită ca previzualizare și în datele structurate.</span>
                @error('newImages.*') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            @if($shop && $shop->media->isNotEmpty())
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    @foreach($shop->media as $medium)
                        <figure class="overflow-hidden rounded-xl border border-stone-200" wire:key="medium-{{ $medium->id }}">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}"
                                 alt="{{ $medium->alt_text }}" class="aspect-4/3 w-full bg-stone-100 object-cover">

                            <figcaption class="flex items-center justify-between gap-1 p-2">
                                <div class="flex gap-1">
                                    <button type="button" wire:click="moveImage({{ $medium->id }}, -1)"
                                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900"
                                            aria-label="Mută mai devreme">←</button>
                                    <button type="button" wire:click="moveImage({{ $medium->id }}, 1)"
                                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900"
                                            aria-label="Mută mai târziu">→</button>
                                </div>

                                <button type="button" wire:click="removeImage({{ $medium->id }})"
                                        wire:confirm="Ștergi imaginea?"
                                        class="rounded-lg px-2 py-1 text-xs font-semibold text-red-700 transition hover:bg-red-50">Șterge</button>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif
        </section>

        <section x-show="tab === 'profile'" x-cloak class="space-y-6">
            <label class="block max-w-xl">
                <span class="field-label">Mărci deservite</span>
                <select wire:model="makeIds" multiple size="10">
                    @foreach($makes as $make)<option value="{{ $make->id }}">{{ $make->name }}</option>@endforeach
                </select>
                <span class="field-hint">Lăsat gol înseamnă „orice marcă”.</span>
            </label>

            @foreach([
                ['amenities', 'Dotări', $amenityOptions],
                ['paymentMethods', 'Metode de plată', $paymentOptions],
                ['certifications', 'Certificări', $certificationOptions],
            ] as [$property, $legend, $options])
                <fieldset class="space-y-2">
                    <legend class="field-label">{{ $legend }}</legend>

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($options as $key => $label)
                            <label class="flex items-center gap-2 rounded-lg bg-stone-50 p-3 text-sm text-stone-700">
                                <input type="checkbox" value="{{ $key }}" wire:model="{{ $property }}"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </section>

        <section x-show="tab === 'promotion'" x-cloak class="space-y-4">
            {{-- Written where the tier is chosen, because the person setting it is the one who
                 has to know it will be declared to every reader. --}}
            <p class="rounded-lg bg-stone-50 p-4 text-sm text-stone-600">
                Orice nivel plătit este afișat public ca atare, pe card și pe fișă, și influențează
                ordinea în director. Legea cere ca poziționarea plătită să fie declarată cititorului.
            </p>

            <div class="grid gap-4 md:grid-cols-3">
                <label class="block">
                    <span class="field-label">Nivel</span>
                    <select wire:model.live="promotionTier">
                        @foreach($tiers as $tier)<option value="{{ $tier->value }}">{{ $tier->label() }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Valabil până la</span>
                    <input type="date" wire:model="promotedUntil" @disabled($promotionTier === 'none')>
                    <span class="field-hint">După această dată listarea nu mai urcă în ordine.</span>
                    @error('promotedUntil') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Notă contract</span>
                    <input wire:model="promotionNotes">
                </label>
            </div>

            @if($shop)
                <x-admin.section title="Lead-uri primite">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr><th>Tip</th><th class="text-right">Luna aceasta</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            @foreach(\App\Enums\ServiceLeadEventType::cases() as $type)
                                <tr>
                                    <td>{{ $type->label() }}</td>
                                    <td class="text-right tabular-nums">{{ $leadTotals['month'][$type->value] ?? 0 }}</td>
                                    <td class="text-right tabular-nums">{{ $leadTotals['all'][$type->value] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-admin.section>
            @endif
        </section>

        <div class="border-t border-stone-100 pt-5">
            <button type="submit" class="btn-primary">Salvează service-ul</button>
        </div>
    </form>
</div>
