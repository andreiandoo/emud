@props(['menu'])

{{-- The catalogue, three levels deep. The left rail holds every main category; pointing at one
     swaps the right side to its own tree, so moving between sixteen categories costs nothing
     and never stacks two panels. Clicking a main category still follows its link, so the whole
     category stays one step away. `mega` and `category` live in the header's Alpine scope. --}}
<div id="st-mega" class="st-drop hidden lg:block" :class="mega && 'is-open'"
     @click.outside="if (! $event.target.closest('[aria-controls=st-mega]')) mega = false">
    <div class="shell grid max-h-[calc(100vh-var(--st-header-h))] grid-cols-[17rem_minmax(0,1fr)] gap-10 overflow-y-auto py-8" data-lenis-prevent>
        <ul class="st-mega-rail grid content-start gap-0.5 border-r border-gl pr-4">
            @foreach($menu as $top)
                <li>
                    <a href="{{ route('storefront.category', $top['path']) }}"
                       @mouseenter="category = {{ $top['id'] }}" @focus="category = {{ $top['id'] }}"
                       :aria-current="category === {{ $top['id'] }} ? 'true' : 'false'"
                       class="flex items-center gap-3 rounded-[3px] px-3 py-2 text-[14px] text-[#cfcdc6] transition hover:text-bone">
                        <x-storefront.icon :name="$top['icon']" class="h-[18px] w-[18px] shrink-0 text-sand" />
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $top['name'] }}</span>
                        <span class="st-mega-dot h-1.5 w-1.5 shrink-0 rounded-full transition-colors"></span>
                    </a>
                </li>
            @endforeach

            <li class="mt-3 border-t border-gl px-3 pt-3">
                <a href="{{ route('storefront.search') }}" class="st-link text-mute hover:text-bone">
                    Tot catalogul <x-storefront.icon name="arrow-right" />
                </a>
            </li>
        </ul>

        @foreach($menu as $top)
            @php($image = $top['image'] ?? $top['children']->pluck('image')->filter()->first())

            <div x-show="category === {{ $top['id'] }}" x-cloak class="col-start-2 row-start-1 min-w-0">
                <div class="mb-7 flex items-end justify-between gap-6 border-b border-gl pb-5">
                    <h2 class="st-display text-[2.4rem] text-bone">{{ $top['name'] }}</h2>

                    <a href="{{ route('storefront.category', $top['path']) }}" class="st-link shrink-0 text-bone">
                        Vezi toată categoria <x-storefront.icon name="arrow-right" />
                    </a>
                </div>

                <div class="grid gap-10 {{ $image ? 'xl:grid-cols-[minmax(0,1fr)_17rem]' : '' }}">
                    @if($top['children']->isEmpty())
                        <p class="text-sm text-mute">Categoria nu are încă subcategorii.</p>
                    @else
                        {{-- Columns rather than a grid: groups differ wildly in length (three
                             leaves under one, twenty under the next), and columns pack them
                             without leaving holes. --}}
                        <div class="min-w-0 columns-2 gap-10 xl:columns-3">
                            @foreach($top['children'] as $group)
                                <div class="mb-7 break-inside-avoid">
                                    <a href="{{ route('storefront.category', $group['path']) }}"
                                       class="block font-display text-[17px] font-semibold leading-tight text-bone transition hover:text-signal">
                                        {{ $group['name'] }}
                                    </a>

                                    @if($group['children']->isNotEmpty())
                                        <ul class="mt-2.5 grid gap-1.5">
                                            @foreach($group['children'] as $leaf)
                                                <li>
                                                    <a href="{{ route('storefront.category', $leaf['path']) }}" class="block text-[13.5px] text-mute transition hover:text-bone">
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
                        <a href="{{ route('storefront.category', $top['path']) }}" class="st-tile group hidden aspect-[4/5] flex-col justify-end p-5 xl:flex">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}" alt="" loading="lazy" class="st-media">
                            <span class="st-shade"></span>
                            <span class="font-display text-2xl font-semibold leading-tight text-bone">{{ $top['name'] }}</span>
                            <span class="st-underline mt-3 flex items-center justify-between border-t border-white/20 pt-3 text-[12px] font-semibold uppercase tracking-[.12em] text-bone">
                                Descoperă <x-storefront.icon name="arrow-right" class="h-4 w-4" />
                            </span>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
