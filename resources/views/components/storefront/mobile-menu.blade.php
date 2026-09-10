@props(['menu'])

{{-- The phone menu fills the screen and drills down one level at a time: sixteen categories with
     their own trees do not survive being folded into a single long accordion. `mobile` comes
     from the header's Alpine scope; `level` is which category is open, null for the start. --}}
<div class="st-drawer lg:hidden" :class="mobile && 'is-open'" x-data="{ level: null }" x-effect="if (! mobile) level = null" data-lenis-prevent>
    <div class="h-full overflow-y-auto overscroll-contain">
        <div class="shell pb-16 pt-6">
            <div x-show="level === null" class="st-sheet">
                <p class="st-kicker mb-3 text-mute">Piese & accesorii</p>

                <ul class="border-t border-gl">
                    @foreach($menu as $top)
                        <li class="border-b border-gl">
                            @if($top['children']->isNotEmpty())
                                <button type="button" @click="level = {{ $top['id'] }}" class="flex w-full items-center gap-4 py-4 text-left">
                                    <x-storefront.icon :name="$top['icon']" class="h-5 w-5 shrink-0 text-sand" />
                                    <span class="min-w-0 flex-1 truncate font-display text-[22px] font-semibold leading-tight">{{ $top['name'] }}</span>
                                    <x-storefront.icon name="chevron-right" class="h-5 w-5 shrink-0 text-mute" />
                                </button>
                            @else
                                <a href="{{ route('storefront.category', $top['path']) }}" class="flex items-center gap-4 py-4">
                                    <x-storefront.icon :name="$top['icon']" class="h-5 w-5 shrink-0 text-sand" />
                                    <span class="min-w-0 flex-1 truncate font-display text-[22px] font-semibold leading-tight">{{ $top['name'] }}</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <nav class="mt-10 grid gap-1" aria-label="Pagini">
                    @foreach([
                        ['storefront.collections', 'Colecții pe model de mașină'],
                        ['storefront.services', 'Service auto'],
                        ['storefront.guides', 'Ghiduri'],
                        ['storefront.contact', 'Contact'],
                    ] as [$name, $label])
                        <a href="{{ route($name) }}" class="flex items-center justify-between py-2.5 text-lg text-[#d4d3ce]">
                            {{ $label }} <x-storefront.icon name="arrow-right" class="h-4 w-4 text-mute" />
                        </a>
                    @endforeach
                </nav>

                <div class="mt-10 border-t border-gl pt-6">
                    @auth
                        <p class="st-kicker mb-3 text-mute">Contul meu</p>
                        <nav class="grid grid-cols-2 gap-2" aria-label="Contul meu">
                            @foreach([
                                ['customer.garage', 'Garajul meu', 'car'],
                                ['customer.orders', 'Comenzile mele', 'box'],
                                ['customer.appointments', 'Programări', 'calendar'],
                                ['customer.favourites', 'Favorite', 'heart'],
                                ['customer.profile', 'Datele mele', 'user'],
                                ['customer.dashboard', 'Prezentare', 'grid'],
                            ] as [$name, $label, $icon])
                                <a href="{{ route($name) }}" class="flex items-center gap-2.5 rounded-[3px] border border-gl px-3 py-3 text-sm">
                                    <x-storefront.icon :name="$icon" class="h-4 w-4 text-sand" /> {{ $label }}
                                </a>
                            @endforeach
                        </nav>

                        <form method="post" action="{{ route('customer.logout') }}" class="mt-4">
                            @csrf
                            <button type="submit" class="text-sm text-mute underline underline-offset-2">Ieși din cont</button>
                        </form>
                    @else
                        <div class="grid grid-cols-2 gap-2">
                            <a href="{{ route('customer.login') }}" class="st-btn st-btn--ghost">Autentificare</a>
                            <a href="{{ route('customer.register') }}" class="st-btn">Cont nou</a>
                        </div>
                    @endauth
                </div>
            </div>

            @foreach($menu as $top)
                @continue($top['children']->isEmpty())

                <div x-show="level === {{ $top['id'] }}" x-cloak class="st-sheet">
                    <button type="button" @click="level = null" class="mb-6 flex items-center gap-2 text-sm text-mute">
                        <x-storefront.icon name="arrow-left" class="h-4 w-4" /> Toate categoriile
                    </button>

                    <div class="mb-6 flex items-end justify-between gap-4">
                        <h2 class="st-display text-[2.2rem]">{{ $top['name'] }}</h2>
                    </div>

                    <a href="{{ route('storefront.category', $top['path']) }}" class="st-btn st-btn--block mb-8">
                        Vezi toată categoria <x-storefront.icon name="arrow-right" class="st-arrow" />
                    </a>

                    <div class="grid gap-7">
                        @foreach($top['children'] as $group)
                            <div>
                                <a href="{{ route('storefront.category', $group['path']) }}" class="block font-display text-lg font-semibold">{{ $group['name'] }}</a>

                                @if($group['children']->isNotEmpty())
                                    <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5">
                                        @foreach($group['children'] as $leaf)
                                            <li><a href="{{ route('storefront.category', $leaf['path']) }}" class="block text-sm text-mute">{{ $leaf['name'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
