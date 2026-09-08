@php($menu = app(\App\Storefront\CategoryMenu::class)->tree())

@if($menu->isNotEmpty())
    {{-- One Alpine scope for the whole bar: only one panel is ever open, and moving between
         top-level items swaps panels instead of stacking them. --}}
    <nav class="border-t border-stone-100" x-data="{ open: null }" @keydown.escape.window="open = null">
        <div class="mx-auto max-w-6xl px-4">
            <ul class="flex gap-1 overflow-x-auto text-sm">
                @foreach($menu as $top)
                    <li class="shrink-0">
                        <button type="button"
                                @click="open = (open === {{ $top['id'] }} ? null : {{ $top['id'] }})"
                                :aria-expanded="open === {{ $top['id'] }} ? 'true' : 'false'"
                                aria-haspopup="true"
                                @class(['flex items-center gap-1 whitespace-nowrap px-3 py-2.5 transition'])
                                :class="open === {{ $top['id'] }} ? 'text-stone-900 font-semibold' : 'text-stone-600 hover:text-stone-900'">
                            {{ $top['name'] }}
                            @if($top['children']->isNotEmpty())
                                <span class="text-xs" aria-hidden="true">▾</span>
                            @endif
                        </button>
                    </li>
                @endforeach

                <li class="shrink-0"><a href="{{ route('storefront.services') }}" class="block whitespace-nowrap px-3 py-2.5 text-stone-600 hover:text-stone-900">Service auto</a></li>
                <li class="shrink-0"><a href="{{ route('storefront.guides') }}" class="block whitespace-nowrap px-3 py-2.5 text-stone-600 hover:text-stone-900">Ghiduri</a></li>
                <li class="shrink-0"><a href="{{ route('storefront.contact') }}" class="block whitespace-nowrap px-3 py-2.5 text-stone-600 hover:text-stone-900">Contact</a></li>
            </ul>
        </div>

        @foreach($menu as $top)
            @if($top['children']->isNotEmpty())
                <div x-show="open === {{ $top['id'] }}" x-cloak x-transition.opacity
                     @click.outside="open = null"
                     class="border-t border-stone-200 bg-white shadow-lg">
                    <div class="mx-auto max-w-6xl px-4 py-6">
                        <div class="mb-4 flex items-baseline justify-between gap-3">
                            <h2 class="text-lg font-bold">{{ $top['name'] }}</h2>
                            <a href="{{ route('storefront.category', $top['path']) }}" class="text-sm font-semibold underline">
                                Vezi toată categoria
                            </a>
                        </div>

                        <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach($top['children'] as $group)
                                <div>
                                    <a href="{{ route('storefront.category', $group['path']) }}" class="block font-semibold hover:underline">
                                        {{ $group['name'] }}
                                    </a>

                                    @if($group['children']->isNotEmpty())
                                        <ul class="mt-2 space-y-1 text-sm">
                                            @foreach($group['children'] as $leaf)
                                                <li>
                                                    <a href="{{ route('storefront.category', $leaf['path']) }}" class="text-stone-600 hover:text-stone-900 hover:underline">
                                                        {{ $leaf['name'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
@endif
