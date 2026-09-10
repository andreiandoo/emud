@props(['overlay' => false])

@php($settings = app(\App\Settings\StoreSettings::class))
@php($menu = app(\App\Storefront\CategoryMenu::class)->tree())
@php($logoPath = $settings->string('logo_path'))
@php($siteTitle = $settings->string('site_title', 'eMUD'))
@php($phone = $settings->string('contact_phone'))
@php($topbarMessage = $settings->string('topbar_message'))
@php($promoEnabled = $settings->bool('promo_enabled') && $settings->string('promo_message') !== '')
{{-- Read before the Alpine scope rather than inside it: an empty catalogue makes first() null,
     and subscripting that is a warning Laravel turns into an exception. --}}
@php($firstCategoryId = $menu->first()['id'] ?? null)

{{-- The wrapper holds the Alpine state for the bar and for the phone menu. It has no box of its
     own (display: contents) for two reasons: a box around a sticky element stops it sticking
     past its own height, and the phone menu has to sit outside the bar, because a blurred bar
     becomes the containing block of anything fixed inside it. --}}
<div class="contents"
     x-data="{
         mega: false,
         category: {{ $firstCategoryId ?? 'null' }},
         mobile: false,
         search: false,
         account: false,
         hover: window.matchMedia('(hover: hover)').matches,
         closeAll() { this.mega = false; this.mobile = false; this.search = false; this.account = false },
     }"
     @keydown.escape.window="closeAll()"
     x-effect="document.documentElement.classList.toggle('st-locked', mobile)">

    <header data-st-header :data-open="(mega || mobile || search || account) ? '1' : '0'"
            @mouseleave="if (hover) mega = false"
            @class(['st-header', 'is-overlay' => $overlay])>

        {{-- A campaign the visitor can close. Signal orange, because it is the one thing up here
             that changes from week to week. --}}
        @if($promoEnabled)
            <div x-data="{
                     version: @js($settings->string('promo_version')),
                     visible: false,
                     init() {
                         {{-- Storage throws outright in some privacy modes, so a failure shows the
                              bar rather than swallowing the campaign. --}}
                         try { this.visible = localStorage.getItem('emud.promo') !== this.version }
                         catch (error) { this.visible = true }
                     },
                     dismiss() {
                         this.visible = false
                         try { localStorage.setItem('emud.promo', this.version) } catch (error) {}
                     },
                 }"
                 x-show="visible" x-cloak x-collapse class="bg-signal text-ink">
                <div class="shell flex items-center gap-3 py-2 text-[13px] font-medium">
                    <p class="min-w-0 flex-1 text-center">
                        {{ $settings->string('promo_message') }}
                        @if($settings->string('promo_link_url') !== '')
                            <a href="{{ $settings->string('promo_link_url') }}" class="ml-1 font-semibold underline underline-offset-2">
                                {{ $settings->string('promo_link_label', 'Vezi') }}
                            </a>
                        @endif
                    </p>

                    <button type="button" @click="dismiss()" class="shrink-0 rounded-full p-1 transition hover:bg-ink/10" aria-label="Închide anunțul">
                        <x-storefront.icon name="close" class="h-4 w-4" />
                    </button>
                </div>
            </div>
        @endif

        {{-- 1. Utility bar: the standing promises, read once and then ignored, so they are small. --}}
        <div class="border-b border-white/[.07] text-[12.5px] text-[#c9c8c2]">
            <div class="shell flex h-9 items-center gap-6">
                @if($topbarMessage !== '')
                    <p class="min-w-0 truncate">
                        {{ $topbarMessage }}
                        @if($settings->string('topbar_link_url') !== '')
                            <a href="{{ $settings->string('topbar_link_url') }}" class="ml-1 font-semibold text-bone underline underline-offset-2 transition hover:text-signal">
                                {{ $settings->string('topbar_link_label', 'Detalii') }}
                            </a>
                        @endif
                    </p>
                @endif

                <div class="ml-auto hidden shrink-0 items-center gap-6 md:flex">
                    @if($phone !== '')
                        <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="flex items-center gap-1.5 transition hover:text-bone">
                            <x-storefront.icon name="phone" class="h-3.5 w-3.5" /> {{ $phone }}
                        </a>
                    @endif

                    <a href="{{ route('storefront.services') }}" class="flex items-center gap-1.5 transition hover:text-bone">
                        <x-storefront.icon name="pin" class="h-3.5 w-3.5" /> Găsește un service
                    </a>
                </div>
            </div>
        </div>

        {{-- 2. The bar itself, and the panels it anchors. data-st-header-main marks where the bar
                starts once scrolled: everything above it slides out of view (index.js). --}}
        <div class="relative" data-st-header-main>
            <div class="shell flex h-[4.75rem] items-center gap-1">
                <a href="{{ route('storefront.home') }}" class="mr-3 flex shrink-0 items-center gap-2.5 lg:mr-5" aria-label="{{ $siteTitle }}, prima pagină">
                    @if($logoPath !== '')
                        {{-- Drawn white: the bar is graphite on every page. --}}
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}"
                             alt="{{ $siteTitle }}" class="h-9 w-auto brightness-0 invert">
                    @else
                        <span class="h-[11px] w-[11px] rounded-[1px] bg-signal"></span>
                        <span class="font-display text-[26px] font-bold leading-none tracking-[.04em]">{{ $siteTitle }}</span>
                    @endif
                </a>

                <nav class="hidden h-full items-stretch lg:flex" aria-label="Principal">
                    @if($menu->isNotEmpty())
                        <button type="button" class="st-nav-link" aria-controls="st-mega" :aria-expanded="mega ? 'true' : 'false'"
                                @click="const open = ! mega; closeAll(); mega = open"
                                @mouseenter="if (hover && ! mega) { closeAll(); mega = true }">
                            Piese & accesorii
                            <x-storefront.icon name="chevron-down" class="h-3.5 w-3.5 transition-transform duration-500" ::class="mega && 'rotate-180'" />
                        </button>
                    @endif

                    @foreach([
                        ['storefront.collections', 'Colecții', 'storefront.collection*'],
                        ['storefront.services', 'Service auto', 'storefront.service*'],
                        ['storefront.guides', 'Ghiduri', 'storefront.guide*'],
                        ['storefront.contact', 'Contact', 'storefront.contact'],
                    ] as [$route, $label, $pattern])
                        <a href="{{ route($route) }}" class="st-nav-link" @mouseenter="if (hover) mega = false"
                           @if(request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-0.5">
                    <button type="button" class="st-icon-btn" aria-label="Caută" aria-controls="st-search" :aria-expanded="search ? 'true' : 'false'"
                            @click="const open = ! search; closeAll(); search = open; if (open) setTimeout(() => $refs.query.focus(), 200)">
                        <x-storefront.icon name="search" class="h-5 w-5" />
                    </button>

                    <div class="mx-1">
                        <livewire:storefront.vehicle-selector />
                    </div>

                    {{-- Account is a menu when signed in and a plain link when not: a dropdown with
                         one item in it is a worse button. --}}
                    @auth
                        <div class="relative" @click.outside="account = false">
                            <button type="button" class="st-icon-btn" aria-label="Contul meu" :aria-expanded="account ? 'true' : 'false'"
                                    @click="const open = ! account; closeAll(); account = open">
                                <x-storefront.icon name="user" class="h-5 w-5" />
                            </button>

                            <div x-show="account" x-cloak x-transition.opacity.duration.200ms
                                 class="absolute right-0 top-full z-50 mt-3 w-64 overflow-hidden rounded-[3px] border border-gl2 bg-g1 py-2 shadow-2xl">
                                <div class="border-b border-gl px-4 pb-3 pt-1.5">
                                    <p class="st-kicker text-mute">Contul meu</p>
                                    <p class="mt-2 truncate font-semibold text-bone">{{ auth()->user()->name }}</p>
                                </div>

                                <nav class="py-1">
                                    @foreach([
                                        ['customer.dashboard', 'Prezentare', 'grid'],
                                        ['customer.garage', 'Garajul meu', 'car'],
                                        ['customer.orders', 'Comenzile mele', 'box'],
                                        ['customer.appointments', 'Programările mele', 'calendar'],
                                        ['customer.favourites', 'Favorite', 'heart'],
                                        ['customer.profile', 'Datele mele', 'user'],
                                    ] as [$name, $label, $icon])
                                        <a href="{{ route($name) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#d4d3ce] transition hover:bg-white/5 hover:text-bone">
                                            <x-storefront.icon :name="$icon" class="h-4 w-4 text-mute" /> {{ $label }}
                                        </a>
                                    @endforeach
                                </nav>

                                <form method="post" action="{{ route('customer.logout') }}" class="border-t border-gl pt-1">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-[#d4d3ce] transition hover:bg-white/5 hover:text-bone">
                                        <x-storefront.icon name="logout" class="h-4 w-4 text-mute" /> Ieși din cont
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('customer.login') }}" class="st-icon-btn max-sm:hidden" aria-label="Autentificare">
                            <x-storefront.icon name="user" class="h-5 w-5" />
                        </a>
                    @endauth

                    <livewire:storefront.cart-badge />

                    <button type="button" class="st-icon-btn lg:hidden" aria-label="Meniu" :aria-expanded="mobile ? 'true' : 'false'"
                            @click="const open = ! mobile; closeAll(); mobile = open">
                        <x-storefront.icon name="menu" class="h-6 w-6" x-show="! mobile" />
                        <x-storefront.icon name="close" class="h-6 w-6" x-show="mobile" x-cloak />
                    </button>
                </div>
            </div>

            {{-- Search opens across the whole width: a part number is long, and the box has to
                 hold it at a size that can be read back before pressing enter. --}}
            <div id="st-search" class="st-drop" :class="search && 'is-open'"
                 @click.outside="if (! $event.target.closest('[aria-controls=st-search]')) search = false">
                <form action="{{ route('storefront.search') }}" method="get" class="shell grid gap-5 py-7">
                    <label class="flex items-center gap-4 border-b border-gl2 pb-3">
                        <span class="sr-only">Caută în catalog</span>
                        <x-storefront.icon name="search" class="h-7 w-7 shrink-0 text-mute" />
                        <input x-ref="query" type="search" name="q" value="{{ request('q') }}" placeholder="Cod piesă, MPN sau denumire" autocomplete="off"
                               class="h-auto min-w-0 flex-1 rounded-none border-0 bg-transparent p-0 font-display text-[clamp(1.5rem,3vw,2.5rem)] font-medium tracking-tight text-bone placeholder:text-mute2 focus:border-0 focus:ring-0">
                        <button type="submit" class="st-btn st-btn--sm max-sm:hidden">Caută</button>
                    </label>

                    <div class="flex flex-wrap items-center gap-2 text-sm text-mute">
                        <span class="mr-1">Sau găsește mașina după</span>
                        <button type="button" class="st-chip text-bone" @click="closeAll(); $dispatch('open-vehicle-selector', { tab: 'vin' })">
                            <x-storefront.icon name="vin" class="h-4 w-4" /> Serie de șasiu (VIN)
                        </button>
                        <button type="button" class="st-chip text-bone" @click="closeAll(); $dispatch('open-vehicle-selector', { tab: 'car' })">
                            <x-storefront.icon name="car" class="h-4 w-4" /> Marcă și model
                        </button>
                        <a href="{{ route('storefront.collections') }}" class="st-chip text-bone">Toate colecțiile</a>
                    </div>
                </form>
            </div>

            @if($menu->isNotEmpty())
                <x-storefront.mega-menu :menu="$menu" />
            @endif
        </div>
    </header>

    <x-storefront.mobile-menu :menu="$menu" />
</div>
