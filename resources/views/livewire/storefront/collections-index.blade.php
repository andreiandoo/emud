<div class="space-y-10">
    <x-seo title="Colecții pe model de mașină"
           description="Alege-ți mașina și vezi tot ce avem pentru ea: piese, accesorii off-road și echipare."
           :canonical="route('storefront.collections')" />

    <section>
        <h1 class="text-3xl font-black tracking-tight">Alege-ți mașina</h1>
        <p class="mt-2 max-w-2xl text-stone-600">
            Fiecare colecție adună tot ce ținem pe stoc pentru un model — de la revizie la echipare completă.
        </p>
    </section>

    @if($featured->isNotEmpty())
        <x-storefront.choose-your-ride title="Cele mai căutate" subtitle="" :limit="12" />
    @endif

    <section class="space-y-4">
        <label class="block max-w-sm">
            <span class="sr-only">Caută un model</span>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Caută marca sau modelul…">
        </label>

        @if($groups->isEmpty())
            <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
                Nicio colecție care să se potrivească.
            </p>
        @else
            <div class="space-y-8">
                @foreach($groups as $group)
                    <div>
                        <h2 class="mb-3 border-b border-stone-200 pb-2 text-lg font-bold">
                            @if($group['make'])
                                <a href="{{ $group['make']->url() }}" class="hover:underline">{{ $group['name'] }}</a>
                            @else
                                {{ $group['name'] }}
                            @endif
                        </h2>

                        @if($group['models']->isEmpty())
                            <p class="text-sm text-stone-500">Doar colecția de marcă.</p>
                        @else
                            <ul class="grid gap-x-6 gap-y-1.5 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach($group['models'] as $model)
                                    <li>
                                        <a href="{{ $model->url() }}" class="text-sm text-stone-700 hover:text-stone-900 hover:underline">
                                            {{ $model->name }}
                                            @if($model->yearRange())
                                                <span class="text-stone-400">· {{ $model->yearRange() }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
