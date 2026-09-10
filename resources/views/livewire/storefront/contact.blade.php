@php($settings = app(\App\Settings\StoreSettings::class))
@php($phone = $settings->string('contact_phone'))
@php($email = $settings->string('contact_email'))
@php($hours = $settings->string('opening_hours'))

<div>
    <x-seo title="Contact" description="Scrie-ne despre o comandă, o piesă sau compatibilitatea cu mașina ta." />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pb-12 pt-16 sm:pt-24">
            <p class="st-kicker text-mute">Contact</p>
            <h1 class="st-display mt-5 max-w-[14ch] text-[clamp(2.6rem,6vw,5.75rem)] leading-[.92]">Contact</h1>
            <p class="mt-5 max-w-[56ch] text-[clamp(1rem,1.2vw,1.15rem)] text-[#cfcdc6]">
                Scrie-ne despre o comandă, o piesă sau compatibilitatea cu mașina ta. Îți răspundem pe email.
            </p>
        </div>
    </section>

    <div class="shell grid gap-10 pb-24 pt-12 sm:pt-16 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="grid content-start gap-6">
            @if($sent)
                <p class="flex items-start gap-2.5 rounded-[3px] bg-sand px-4 py-3 text-sm text-sandink">
                    <x-storefront.icon name="check" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $sent }}
                </p>
            @endif

            <form wire:submit="send" class="grid gap-5 rounded-[3px] bg-white p-6 sm:p-8">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="field-label">Nume</span>
                        <input type="text" wire:model="name" autocomplete="name">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">Email</span>
                        <input type="email" wire:model="email" autocomplete="email">
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">Telefon <span class="font-normal opacity-70">(opțional)</span></span>
                        <input type="tel" wire:model="phone" autocomplete="tel">
                    </label>
                    <label class="block">
                        <span class="field-label">Subiect <span class="font-normal opacity-70">(opțional)</span></span>
                        <input type="text" wire:model="subject" placeholder="Ex.: kit de înălțare pentru Hilux">
                    </label>
                </div>

                <label class="block">
                    <span class="field-label">Mesaj</span>
                    <textarea wire:model="message" rows="6" placeholder="Spune-ne mașina (marcă, model, an) și ce cauți."></textarea>
                    @error('message') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                {{-- Hidden from people, visible to bots. aria-hidden and tabindex keep it out of the
                     way of screen readers and keyboard navigation. --}}
                <div class="hidden" aria-hidden="true">
                    <label>Website<input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div>
                    <button type="submit" class="st-btn">
                        <span wire:loading.remove wire:target="send">Trimite mesajul</span>
                        <span wire:loading wire:target="send">Se trimite…</span>
                    </button>
                </div>
            </form>
        </div>

        <aside class="grid content-start gap-4">
            <div class="grid gap-4 rounded-[3px] bg-g0 p-6 text-bone">
                <p class="st-kicker text-mute">Direct</p>
                @if($phone !== '')
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="flex items-center gap-3 font-display text-2xl font-semibold transition hover:text-signal">
                        <x-storefront.icon name="phone" class="h-5 w-5 text-sand" /> {{ $phone }}
                    </a>
                @endif
                @if($email !== '')
                    <a href="mailto:{{ $email }}" class="flex items-center gap-3 text-[#d8d6cf] transition hover:text-bone">
                        <x-storefront.icon name="mail" class="h-5 w-5 text-sand" /> {{ $email }}
                    </a>
                @endif
                @if($hours !== '')
                    <p class="flex items-start gap-3 text-sm text-mute"><x-storefront.icon name="calendar" class="mt-0.5 h-5 w-5 shrink-0 text-sand" /> {{ $hours }}</p>
                @endif
            </div>

            <div class="grid gap-3 rounded-[3px] bg-sand p-6 text-ink">
                <p class="font-display text-xl font-semibold">Întrebi de compatibilitate?</p>
                <p class="text-sm text-sandink">Cel mai repede o afli din seria de șasiu. Alege mașina din header și filtrăm tot catalogul pentru ea.</p>
                <button type="button" @click="$dispatch('open-vehicle-selector', { tab: 'vin' })" class="st-btn st-btn--ink w-fit">Caută după VIN</button>
            </div>
        </aside>
    </div>
</div>
