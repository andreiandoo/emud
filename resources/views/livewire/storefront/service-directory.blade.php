<div class="space-y-6">
    <x-seo title="Service auto în România"
           description="Ateliere și service-uri auto din România, filtrate după județ, oraș și specializare. Off-road, suspensie, diagnoză, anvelope." />

    <div>
        <h1 class="text-2xl font-black tracking-tight">Service auto în România</h1>
        <p class="mt-1 text-sm text-stone-600">
            Ateliere pe județe și specializări. Unele listări sunt plătite și sunt marcate ca atare.
        </p>
    </div>

    <div class="grid gap-3 rounded-xl border border-stone-200 bg-white p-4 sm:grid-cols-4">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Județ</span>
            <select wire:model.live="county" class="w-full rounded-lg border-stone-300 text-sm">
                <option value="">Toate</option>
                @foreach($counties as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Oraș</span>
            <select wire:model.live="city" @disabled($cities->isEmpty()) class="w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100">
                <option value="">{{ $cities->isEmpty() ? 'Alege întâi județul' : 'Toate' }}</option>
                @foreach($cities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Specializare</span>
            <select wire:model.live="speciality" class="w-full rounded-lg border-stone-300 text-sm">
                <option value="">Toate</option>
                @foreach($specialities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
            </select>
        </label>

        <label class="flex items-end gap-2 text-sm">
            <input type="checkbox" wire:model.live="fitsOurParts" class="mb-2 rounded border-stone-300">
            <span class="mb-1.5">Montează piese cumpărate de la noi</span>
        </label>
    </div>

    @if($shops->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Niciun service nu corespunde filtrelor alese.
        </p>
    @else
        <div class="space-y-3">
            @foreach($shops as $shop)
                @php($tier = $shop->effectiveTier())
                <div @class([
                    'rounded-xl border bg-white p-4',
                    'border-lime-400 ring-1 ring-lime-400' => $tier->isPaid(),
                    'border-stone-200' => ! $tier->isPaid(),
                ])>
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <a href="{{ route('storefront.service', $shop->slug) }}" class="text-lg font-semibold hover:underline">
                            {{ $shop->name }}
                        </a>

                        {{-- Disclosed, not implied by position: ranking that money influenced has
                             to be visible to the reader. --}}
                        @if($tier->isPaid())
                            <span class="rounded-full bg-lime-100 px-2 py-0.5 text-xs font-semibold text-lime-900">
                                {{ $tier->label() }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-0.5 text-sm text-stone-600">
                        {{ $shop->city }}, {{ $shop->county }}
                        @if($shop->fits_parts_bought_here)
                            · <span class="font-semibold text-stone-900">montează piese cumpărate de la noi</span>
                        @endif
                    </p>

                    @if($shop->specialityList())
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($shop->specialityList() as $speciality)
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs">{{ $speciality }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div>{{ $shops->links() }}</div>
    @endif
</div>
