@props(['menu'])

{{-- The same catalogue, folded. A mega panel does not survive a narrow screen: the two columns
     become one long list, so the top level collapses instead and only the opened branch is
     expanded. `mobile` comes from the header block's Alpine scope. --}}
<div x-show="mobile" x-cloak x-collapse class="border-t border-stone-200 lg:hidden">
    <div class="shell max-h-[70vh] overflow-y-auto py-4">
        <nav class="space-y-1" x-data="{ branch: null }">
            @foreach($menu as $top)
                <div>
                    <div class="flex items-stretch gap-1">
                        <a href="{{ route('storefront.category', $top['path']) }}"
                           class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-stone-900 transition hover:bg-stone-100">
                            <x-storefront.icon :name="$top['icon']" class="h-5 w-5 shrink-0 text-stone-400" />
                            <span class="min-w-0 truncate">{{ $top['name'] }}</span>
                        </a>

                        @if($top['children']->isNotEmpty())
                            <button type="button" @click="branch = (branch === {{ $top['id'] }} ? null : {{ $top['id'] }})"
                                    :aria-expanded="branch === {{ $top['id'] }} ? 'true' : 'false'"
                                    class="rounded-lg px-3 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900"
                                    aria-label="Deschide {{ $top['name'] }}">
                                <x-storefront.icon name="chevron-down" class="h-4 w-4 transition-transform"
                                                   ::class="branch === {{ $top['id'] }} && 'rotate-180'" />
                            </button>
                        @endif
                    </div>

                    @if($top['children']->isNotEmpty())
                        <div x-show="branch === {{ $top['id'] }}" x-cloak x-collapse class="ml-8 border-l border-stone-100 pl-4">
                            @foreach($top['children'] as $group)
                                <div class="py-2">
                                    <a href="{{ route('storefront.category', $group['path']) }}" class="block truncate text-sm font-semibold text-stone-800 hover:underline">
                                        {{ $group['name'] }}
                                    </a>

                                    @if($group['children']->isNotEmpty())
                                        <ul class="mt-1 space-y-1">
                                            @foreach($group['children'] as $leaf)
                                                <li>
                                                    <a href="{{ route('storefront.category', $leaf['path']) }}" class="block truncate text-sm text-stone-500 hover:text-stone-900">
                                                        {{ $leaf['name'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </nav>

        <nav class="mt-4 space-y-1 border-t border-stone-100 pt-4">
            @foreach([
                ['storefront.guides', 'Ghiduri'],
                ['storefront.services', 'Service auto'],
                ['storefront.contact', 'Contact'],
            ] as [$name, $label])
                <a href="{{ route($name) }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-100">{{ $label }}</a>
            @endforeach
        </nav>

        <nav class="mt-4 space-y-1 border-t border-stone-100 pt-4">
            @auth
                @foreach([
                    ['customer.dashboard', 'Contul meu'],
                    ['customer.orders', 'Comenzile mele'],
                    ['customer.garage', 'Garajul meu'],
                    ['customer.favourites', 'Favorite'],
                ] as [$name, $label])
                    <a href="{{ route($name) }}" class="block rounded-lg px-3 py-2.5 text-sm text-stone-700 transition hover:bg-stone-100">{{ $label }}</a>
                @endforeach

                <form method="post" action="{{ route('customer.logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm text-stone-700 transition hover:bg-stone-100">Ieși din cont</button>
                </form>
            @else
                <a href="{{ route('customer.login') }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-100">Autentificare</a>
                <a href="{{ route('customer.register') }}" class="mt-1 block rounded-lg bg-stone-900 px-3 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-stone-700">Cont nou</a>
            @endauth
        </nav>
    </div>
</div>
