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

<footer class="mt-20 bg-stone-950 text-stone-400">
    @if($settings->bool('footer_newsletter_enabled', true))
        {{-- The sign-up sits above the link columns rather than inside one: it is the only thing
             in the footer asking for something, and buried in a column it reads as another link. --}}
        <div class="border-b border-stone-800">
            <div class="shell flex flex-col gap-6 py-10 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-md">
                    <h2 class="text-xl font-black tracking-tight text-white">
                        {{ $settings->string('footer_newsletter_title', 'Intră în echipă') }}
                    </h2>
                    <p class="mt-1 text-sm">
                        {{ $settings->string('footer_newsletter_text', 'Noutăți, teste pe teren și oferte. Fără spam.') }}
                    </p>
                </div>

                <livewire:storefront.newsletter-form />
            </div>
        </div>
    @endif

    <div class="shell grid gap-10 py-12 lg:grid-cols-[18rem_1fr]">
        <div class="space-y-5">
            <a href="{{ route('storefront.home') }}" class="inline-block">
                @if($logoPath !== '')
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}"
                         alt="{{ $siteTitle }}" class="h-9 w-auto brightness-0 invert">
                @else
                    <span class="text-xl font-black tracking-[.2em] text-white">{{ $siteTitle }}</span>
                @endif
            </a>

            @if($settings->string('footer_about') !== '')
                <p class="max-w-xs text-sm leading-relaxed">{{ $settings->string('footer_about') }}</p>
            @endif

            <div class="space-y-1.5 text-sm">
                @if($address !== '')
                    <p class="flex items-start gap-2">
                        <x-storefront.icon name="pin" class="mt-0.5 h-4 w-4 shrink-0" />{{ $address }}
                    </p>
                @endif
                @if($phone !== '')
                    <p><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="flex items-center gap-2 transition hover:text-white">
                        <x-storefront.icon name="phone" class="h-4 w-4 shrink-0" />{{ $phone }}
                    </a></p>
                @endif
                @if($email !== '')
                    <p><a href="mailto:{{ $email }}" class="flex items-center gap-2 transition hover:text-white">
                        <x-storefront.icon name="mail" class="h-4 w-4 shrink-0" />{{ $email }}
                    </a></p>
                @endif
            </div>

            @if($social->isNotEmpty())
                <nav class="flex flex-wrap gap-2" aria-label="Rețele sociale">
                    @foreach($social as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           class="flex h-9 w-9 items-center justify-center rounded-full border border-stone-800 transition hover:border-stone-500 hover:text-white"
                           aria-label="{{ ucfirst($network) }}">
                            <x-storefront.icon :name="$network" class="h-4 w-4" />
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            @if($collections->isNotEmpty())
                <nav>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-white">Mașina ta</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach($collections as $collection)
                            <li><a href="{{ $collection->url() }}" class="transition hover:text-white">{{ $collection->name }}</a></li>
                        @endforeach
                        <li><a href="{{ route('storefront.collections') }}" class="font-semibold text-stone-300 transition hover:text-white">Toate colecțiile</a></li>
                    </ul>
                </nav>
            @endif

            @if($columns['categories']->isNotEmpty())
                <nav>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-white">Catalog</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach($columns['categories'] as $category)
                            <li><a href="{{ route('storefront.category', $category) }}" class="transition hover:text-white">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            <nav>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-white">Service auto</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('storefront.services') }}" class="transition hover:text-white">Găsește un service</a></li>
                    <li><a href="{{ route('storefront.service-types') }}" class="transition hover:text-white">Lucrări și servicii</a></li>
                    <li><a href="{{ route('storefront.guides') }}" class="transition hover:text-white">Ghiduri și articole</a></li>
                    <li><a href="{{ route('customer.garage') }}" class="transition hover:text-white">Garajul meu</a></li>
                </ul>
            </nav>

            <nav>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-white">Ajutor</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('storefront.contact') }}" class="transition hover:text-white">Contact</a></li>
                    <li><a href="{{ route('customer.orders') }}" class="transition hover:text-white">Comenzile mele</a></li>
                    @foreach($columns['pages'] as $page)
                        <li><a href="{{ route('storefront.page', $page->slug) }}" class="transition hover:text-white">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </div>

    <div class="border-t border-stone-800">
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
                            <li class="rounded border border-stone-800 px-2 py-1 text-[11px] font-semibold tracking-wide text-stone-400">
                                {{ $methodLabels[$method] }}
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</footer>
