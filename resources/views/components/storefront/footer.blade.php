@php($settings = app(\App\Settings\StoreSettings::class))
@php($columns = app(\App\Storefront\FooterMenu::class)->columns())
@php($collections = app(\App\Storefront\CollectionShowcase::class)->featured(8))
@php($social = collect($settings->array('social_links'))->filter())
@php($payments = collect($settings->array('footer_payment_methods')))
@php($methodLabels = \App\Livewire\Admin\Settings\FooterSettings::PAYMENT_METHODS)
@php($logoPath = $settings->string('logo_path'))
@php($siteTitle = $settings->string('site_title', 'eMUD'))
@php($phone = $settings->string('contact_phone'))
@php($email = $settings->string('contact_email'))
{{-- The registered address doubles as the shop address; there is only one, and asking the
     owner to type it twice would guarantee the two drift apart. --}}
@php($address = collect([
    $settings->string('company_address'),
    $settings->string('company_city'),
    $settings->string('company_county'),
])->filter()->implode(', '))

<footer class="relative isolate overflow-hidden bg-g0 text-mute">
    {{-- Contour lines, as on a topographic map: the shop's quiet signature on dark grounds. --}}
    <canvas data-st-topo="rgba(241,238,230,.05)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

    @if($settings->bool('footer_newsletter_enabled', true))
        {{-- The sign-up sits above the link columns rather than inside one: it is the only thing
             in the footer asking for something, and buried in a column it reads as another link. --}}
        <div id="newsletter" class="shell grid scroll-mt-32 gap-10 border-b border-gl pb-16 pt-24 lg:grid-cols-2 lg:items-end">
            <div class="grid gap-5">
                <p class="st-kicker text-mute">Newsletter</p>
                <h2 class="st-display text-[clamp(2.25rem,4.6vw,4.75rem)] text-bone">
                    {{ $settings->string('footer_newsletter_title', 'Intră în echipă') }}
                </h2>
            </div>

            <div class="grid gap-5">
                <p class="max-w-md text-[15px] leading-relaxed">
                    {{ $settings->string('footer_newsletter_text', 'Noutăți, teste pe teren și oferte. Fără spam.') }}
                </p>
                <livewire:storefront.newsletter-form />
            </div>
        </div>
    @endif

    <div class="shell grid gap-12 py-16 lg:grid-cols-[18rem_1fr]">
        <div class="grid content-start gap-5">
            <a href="{{ route('storefront.home') }}" class="inline-flex items-center gap-2.5">
                @if($logoPath !== '')
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}"
                         alt="{{ $siteTitle }}" class="h-9 w-auto brightness-0 invert">
                @else
                    <span class="h-[11px] w-[11px] rounded-[1px] bg-signal"></span>
                    <span class="font-display text-2xl font-bold tracking-[.04em] text-bone">{{ $siteTitle }}</span>
                @endif
            </a>

            @if($settings->string('footer_about') !== '')
                <p class="max-w-xs text-sm leading-relaxed">{{ $settings->string('footer_about') }}</p>
            @endif

            <div class="grid gap-2 text-sm">
                @if($address !== '')
                    <p class="flex items-start gap-2">
                        <x-storefront.icon name="pin" class="mt-0.5 h-4 w-4 shrink-0 text-sand" />{{ $address }}
                    </p>
                @endif
                @if($phone !== '')
                    <p><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="flex items-center gap-2 transition hover:text-bone">
                        <x-storefront.icon name="phone" class="h-4 w-4 shrink-0 text-sand" />{{ $phone }}
                    </a></p>
                @endif
                @if($email !== '')
                    <p><a href="mailto:{{ $email }}" class="flex items-center gap-2 transition hover:text-bone">
                        <x-storefront.icon name="mail" class="h-4 w-4 shrink-0 text-sand" />{{ $email }}
                    </a></p>
                @endif
            </div>

            @if($social->isNotEmpty())
                <nav class="flex flex-wrap gap-2" aria-label="Rețele sociale">
                    @foreach($social as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           class="grid h-10 w-10 place-items-center rounded-full border border-gl2 transition hover:border-bone hover:bg-bone hover:text-ink"
                           aria-label="{{ ucfirst($network) }}">
                            <x-storefront.icon :name="$network" class="h-4 w-4" />
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            @if($collections->isNotEmpty())
                <nav>
                    <h3 class="mb-4 font-mono text-[11px] uppercase tracking-[.12em] text-mute2">Mașina ta</h3>
                    <ul class="grid gap-2.5 text-[15px] text-[#cfcdc6]">
                        @foreach($collections as $collection)
                            <li><a href="{{ $collection->url() }}" class="transition hover:text-signal">{{ $collection->name }}</a></li>
                        @endforeach
                        <li><a href="{{ route('storefront.collections') }}" class="font-semibold text-bone transition hover:text-signal">Toate colecțiile</a></li>
                    </ul>
                </nav>
            @endif

            @if($columns['categories']->isNotEmpty())
                <nav>
                    <h3 class="mb-4 font-mono text-[11px] uppercase tracking-[.12em] text-mute2">Catalog</h3>
                    <ul class="grid gap-2.5 text-[15px] text-[#cfcdc6]">
                        @foreach($columns['categories'] as $category)
                            <li><a href="{{ route('storefront.category', $category) }}" class="transition hover:text-signal">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            <nav>
                <h3 class="mb-4 font-mono text-[11px] uppercase tracking-[.12em] text-mute2">Service auto</h3>
                <ul class="grid gap-2.5 text-[15px] text-[#cfcdc6]">
                    <li><a href="{{ route('storefront.services') }}" class="transition hover:text-signal">Găsește un service</a></li>
                    <li><a href="{{ route('storefront.service-types') }}" class="transition hover:text-signal">Lucrări și servicii</a></li>
                    <li><a href="{{ route('storefront.guides') }}" class="transition hover:text-signal">Ghiduri și articole</a></li>
                    <li><a href="{{ route('customer.garage') }}" class="transition hover:text-signal">Garajul meu</a></li>
                </ul>
            </nav>

            <nav>
                <h3 class="mb-4 font-mono text-[11px] uppercase tracking-[.12em] text-mute2">Ajutor</h3>
                <ul class="grid gap-2.5 text-[15px] text-[#cfcdc6]">
                    <li><a href="{{ route('storefront.contact') }}" class="transition hover:text-signal">Contact</a></li>
                    <li><a href="{{ route('customer.orders') }}" class="transition hover:text-signal">Comenzile mele</a></li>
                    @foreach($columns['pages'] as $page)
                        <li><a href="{{ route('storefront.page', $page->slug) }}" class="transition hover:text-signal">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </div>

    <p class="st-giant" data-st-giant aria-hidden="true">{{ $siteTitle }}</p>

    <div class="relative border-t border-gl bg-g0">
        <div class="shell flex flex-col gap-4 py-6 text-xs sm:flex-row sm:items-center sm:justify-between">
            <p>
                © {{ now()->year }} {{ $siteTitle }}.
                @if($settings->string('footer_note') !== '')
                    {{ $settings->string('footer_note') }}
                @endif
            </p>

            @if($payments->isNotEmpty())
                {{-- Written out rather than drawn: card scheme logos are trademarks with usage
                     rules attached, and a wrong-coloured Visa mark is worse than the word. --}}
                <ul class="flex flex-wrap items-center gap-2">
                    @foreach($payments as $method)
                        @if(isset($methodLabels[$method]))
                            <li class="rounded-[3px] border border-gl2 px-2 py-1 text-[11px] font-semibold tracking-wide text-mute">
                                {{ $methodLabels[$method] }}
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</footer>
