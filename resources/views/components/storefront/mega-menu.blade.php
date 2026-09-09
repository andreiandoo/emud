@props(['menu'])

{{-- The panel is anchored to the header block, not to the page, so it drops directly under the
     bar at any container width. `category` and `mega` live in that block's Alpine scope: the
     left column only switches which panel is shown, so moving between top categories costs
     nothing and never stacks two panels. --}}
<div x-show="mega" x-cloak x-transition.opacity.duration.150ms @click.outside="mega = false"
     class="absolute inset-x-0 top-full hidden border-b border-stone-200 bg-white shadow-xl lg:block">
    <div class="shell grid grid-cols-[17rem_minmax(0,1fr)] gap-10 py-8">
        {{-- Left: the top level, each with its own icon. Hover switches the panel because that
             is what the customer expects of a mega menu; click follows the link, so the whole
             category is still reachable without a second step. --}}
        <ul class="border-r border-stone-100 pr-4">
            @foreach($menu as $top)
                <li>
                    <a href="{{ route('storefront.category', $top['path']) }}"
                       @mouseenter="category = {{ $top['id'] }}" @focus="category = {{ $top['id'] }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition"
                       :class="category === {{ $top['id'] }} ? 'bg-stone-900 text-white' : 'text-stone-700 hover:bg-stone-100'">
                        <x-storefront.icon :name="$top['icon']" class="h-5 w-5 shrink-0"
                                           ::class="category === {{ $top['id'] }} ? 'text-white' : 'text-stone-400'" />

                        <span class="min-w-0 flex-1 truncate font-semibold">{{ $top['name'] }}</span>

                        <x-storefront.icon name="chevron-right" class="h-4 w-4 shrink-0 opacity-0 transition-opacity"
                                           ::class="category === {{ $top['id'] }} && 'opacity-100'" />
                    </a>
                </li>
            @endforeach

            <li class="mt-2 border-t border-stone-100 pt-2">
                <a href="{{ route('storefront.search') }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-stone-500 transition hover:bg-stone-100 hover:text-stone-900">
                    Vezi tot catalogul
                </a>
            </li>
        </ul>

        {{-- Right: the chosen category's own tree, with its image beside it. --}}
        @foreach($menu as $top)
            @php($image = $top['image'] ?? $top['children']->pluck('image')->filter()->first())

            <div x-show="category === {{ $top['id'] }}" x-cloak class="col-start-2 row-start-1 min-w-0">
                <div class="mb-5 flex items-baseline justify-between gap-4">
                    <h2 class="text-lg font-bold tracking-tight text-stone-900">{{ $top['name'] }}</h2>
                    <a href="{{ route('storefront.category', $top['path']) }}" class="shrink-0 text-sm font-semibold text-stone-600 underline underline-offset-4 hover:text-stone-900">
                        Vezi toată categoria
                    </a>
                </div>

                <div class="grid gap-8 {{ $image ? 'xl:grid-cols-[minmax(0,1fr)_16rem]' : '' }}">
                    @if($top['children']->isEmpty())
                        <p class="text-sm text-stone-500">Categoria nu are încă subcategorii.</p>
                    @else
                        <div class="grid min-w-0 gap-x-8 gap-y-6 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($top['children'] as $group)
                                <div class="min-w-0">
                                    <a href="{{ route('storefront.category', $group['path']) }}" class="block truncate text-sm font-semibold text-stone-900 hover:underline">
                                        {{ $group['name'] }}
                                    </a>

                                    @if($group['children']->isNotEmpty())
                                        <ul class="mt-2 space-y-1.5">
                                            @foreach($group['children'] as $leaf)
                                                <li>
                                                    <a href="{{ route('storefront.category', $leaf['path']) }}" class="block truncate text-sm text-stone-500 transition hover:text-stone-900">
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

                    @if($image)
                        {{-- The image is a link, not decoration: it is the largest thing in the
                             panel and a customer will click it. --}}
                        <a href="{{ route('storefront.category', $top['path']) }}" class="group hidden xl:block">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}"
                                 alt="{{ $top['name'] }}" loading="lazy"
                                 class="aspect-4/3 w-full rounded-xl bg-stone-100 object-cover">
                            <span class="mt-2 flex items-center gap-1 text-sm font-semibold text-stone-900">
                                {{ $top['name'] }}
                                <x-storefront.icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                            </span>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
