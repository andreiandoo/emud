@php($settings = app(\App\Settings\StoreSettings::class))
@php($menu = app(\App\Storefront\CategoryMenu::class)->tree())
@php($cartCount = (int) (app(\App\Storefront\CartManager::class)->current()?->items()->sum('quantity') ?? 0))
@php($logoPath = $settings->string('logo_path'))
@php($siteTitle = $settings->string('site_title', 'eMUD'))
@php($phone = $settings->string('contact_phone'))
@php($topbarMessage = $settings->string('topbar_message'))
@php($promoEnabled = $settings->bool('promo_enabled') && $settings->string('promo_message') !== '')
{{-- Read before the Alpine scope rather than inside it: an empty catalogue makes first() null,
     and subscripting that is a warning Laravel turns into an exception. --}}
@php($firstCategoryId = $menu->first()['id'] ?? null)

<header class="relative z-40">
    {{-- 1. Utility bar. Dark and small: it carries the standing promises — shipping threshold,
            phone, where to get the part fitted — which are read once and then ignored. --}}
    <div class="bg-stone-950 text-stone-300">
        <div class="shell flex h-9 items-center gap-4 text-xs">
            @if($topbarMessage !== '')
                <p class="min-w-0 truncate">
                    {{ $topbarMessage }}
                    @if($settings->string('topbar_link_url') !== '')
                        <a href="{{ $settings->string('topbar_link_url') }}" class="ml-1 font-semibold text-white underline underline-offset-2">
                            {{ $settings->string('topbar_link_label', 'Detalii') }}
                        </a>
                    @endif
                </p>
            @endif

            <div class="ml-auto hidden shrink-0 items-center gap-5 sm:flex">
                @if($phone !== '')
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="flex items-center gap-1.5 transition hover:text-white">
                        <x-storefront.icon name="phone" class="h-3.5 w-3.5" /> {{ $phone }}
                    </a>
                @endif

                <a href="{{ route('storefront.services') }}" class="flex items-center gap-1.5 transition hover:text-white">
                    <x-storefront.icon name="pin" class="h-3.5 w-3.5" /> Găsește un service
                </a>
            </div>
        </div>
    </div>

    {{-- 2. Main row, plus the mega panel it anchors. One Alpine scope for both: the panel is
            positioned against this block, and only one of the menus may be open at a time. --}}
    <div class="relative border-b border-stone-200 bg-white"
         x-data="{ mega: false, category: {{ $firstCategoryId ?? 'null' }}, mobile: false, search: false, account: false }"
         @keydown.escape.window="mega = false; mobile = false; search = false; account = false">

        <div class="shell flex h-20 items-center gap-3">
            <button type="button" @click="mobile = ! mobile" :aria-expanded="mobile ? 'true' : 'false'"
                    class="-ml-2 rounded-lg p-2 text-stone-700 transition hover:bg-stone-100 lg:hidden"
                    aria-label="Meniu">
                <x-storefront.icon name="menu" class="h-6 w-6" x-show="! mobile" />
                <x-storefront.icon name="close" class="h-6 w-6" x-show="mobile" x-cloak />
            </button>

            <a href="{{ route('storefront.home') }}" class="shrink-0">
                @if($logoPath !== '')
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}"
                         alt="{{ $siteTitle }}" class="h-9 w-auto">
                @else
                    <span class="text-xl font-black tracking-[.18em] text-stone-900">{{ $siteTitle }}</span>
                @endif
            </a>

            <nav class="ml-4 hidden h-full items-stretch lg:flex">
                @if($menu->isNotEmpty())
                    <button type="button" @click="mega = ! mega" :aria-expanded="mega ? 'true' : 'false'" aria-haspopup="true"
                            class="flex items-center gap-1.5 px-4 text-sm font-semibold tracking-wide text-stone-900 transition"
                            :class="mega && 'bg-stone-100'">
                        Piese & accesorii
                        <x-storefront.icon name="chevron-down" class="h-4 w-4 text-stone-400 transition-transform" ::class="mega && 'rotate-180'" />
                    </button>
                @endif

                <a href="{{ route('storefront.guides') }}" class="flex items-center px-4 text-sm font-semibold tracking-wide text-stone-600 transition hover:text-stone-900">Ghiduri</a>
                <a href="{{ route('storefront.services') }}" class="flex items-center px-4 text-sm font-semibold tracking-wide text-stone-600 transition hover:text-stone-900">Service auto</a>
                <a href="{{ route('storefront.contact') }}" class="flex items-center px-4 text-sm font-semibold tracking-wide text-stone-600 transition hover:text-stone-900">Contact</a>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <form action="{{ route('storefront.search') }}" method="get" class="relative hidden xl:block">
                    <label for="header-search" class="sr-only">Caută în catalog</label>
                    <x-storefront.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                    <input id="header-search" type="search" name="q" value="{{ request('q') }}"
                           placeholder="Cod piesă, MPN sau denumire" class="h-11 w-72 pl-9">
                </form>

                <button type="button" @click="search = ! search" class="rounded-lg p-2.5 text-stone-700 transition hover:bg-stone-100 xl:hidden" aria-label="Caută">
                    <x-storefront.icon name="search" class="h-5 w-5" />
                </button>

                <livewire:storefront.vehicle-selector />

                {{-- Account is a menu when signed in and a plain link when not: a dropdown with
                     one item in it is a worse button. --}}
                @auth
                    <div class="relative">
                        <button type="button" @click="account = ! account" @click.outside="account = false"
                                :aria-expanded="account ? 'true' : 'false'"
                                class="rounded-lg p-2.5 text-stone-700 transition hover:bg-stone-100" aria-label="Contul meu">
                            <x-storefront.icon name="user" class="h-5 w-5" />
                        </button>

                        <div x-show="account" x-cloak x-transition.opacity.duration.100ms
                             class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-xl">
                            <p class="truncate border-b border-stone-100 px-4 py-2.5 text-sm font-semibold text-stone-900">{{ auth()->user()->name }}</p>

                            @foreach([
                                ['customer.dashboard', 'Contul meu'],
                                ['customer.orders', 'Comenzile mele'],
                                ['customer.garage', 'Garajul meu'],
                                ['customer.favourites', 'Favorite'],
                                ['customer.profile', 'Datele mele'],
                            ] as [$name, $label])
                                <a href="{{ route($name) }}" class="block px-4 py-2 text-sm text-stone-700 transition hover:bg-stone-100">{{ $label }}</a>
                            @endforeach

                            <form method="post" action="{{ route('customer.logout') }}" class="border-t border-stone-100">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-stone-700 transition hover:bg-stone-100">
                                    <x-storefront.icon name="logout" class="h-4 w-4 text-stone-400" /> Ieși din cont
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('customer.login') }}" class="rounded-lg p-2.5 text-stone-700 transition hover:bg-stone-100" aria-label="Autentificare">
                        <x-storefront.icon name="user" class="h-5 w-5" />
                    </a>
                @endauth

                <a href="{{ route('storefront.cart') }}" class="relative rounded-lg p-2.5 text-stone-700 transition hover:bg-stone-100"
                   aria-label="Coșul meu{{ $cartCount > 0 ? ' ('.$cartCount.')' : '' }}">
                    <x-storefront.icon name="cart" class="h-5 w-5" />
                    @if($cartCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-stone-900 px-1 text-[11px] font-bold text-white">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        {{-- Search on narrow screens: a full-width row rather than a shrunken field, because a
             part number is long and the box has to hold it. --}}
        <div x-show="search" x-cloak x-collapse class="border-t border-stone-200 xl:hidden">
            <form action="{{ route('storefront.search') }}" method="get" class="shell relative py-3">
                <label for="header-search-mobile" class="sr-only">Caută în catalog</label>
                <x-storefront.icon name="search" class="pointer-events-none absolute left-7 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                <input id="header-search-mobile" type="search" name="q" value="{{ request('q') }}"
                       placeholder="Cod piesă, MPN sau denumire" class="h-11 pl-9">
            </form>
        </div>

        @if($menu->isNotEmpty())
            <x-storefront.mega-menu :menu="$menu" />
        @endif

        <x-storefront.mobile-menu :menu="$menu" />
    </div>

    {{-- 4. The bar the visitor can close. Kept out of the sticky block above so dismissing it
            gives the page back its full height. --}}
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
             x-show="visible" x-cloak x-collapse class="border-b border-stone-200 bg-stone-100">
            <div class="shell flex items-center gap-3 py-2.5 text-sm">
                <p class="min-w-0 flex-1 text-stone-700">
                    {{ $settings->string('promo_message') }}
                    @if($settings->string('promo_link_url') !== '')
                        <a href="{{ $settings->string('promo_link_url') }}" class="ml-1 font-semibold text-stone-900 underline underline-offset-2">
                            {{ $settings->string('promo_link_label', 'Vezi') }}
                        </a>
                    @endif
                </p>

                <button type="button" @click="dismiss()" class="shrink-0 rounded-lg p-1.5 text-stone-500 transition hover:bg-stone-200 hover:text-stone-900"
                        aria-label="Închide anunțul">
                    <x-storefront.icon name="close" class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif
</header>
