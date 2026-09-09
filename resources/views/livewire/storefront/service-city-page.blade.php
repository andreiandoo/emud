<div class="space-y-8">
    <x-seo :title="'Service auto în '.$cityName"
           :description="'Ateliere și service-uri auto din '.$cityName.'. Program, lucrări, prețuri orientative și cerere de programare online.'"
           :canonical="route('storefront.services.city', $city)" />

    <nav class="flex flex-wrap items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}" class="hover:text-stone-900 hover:underline">Acasă</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('storefront.services') }}" class="hover:text-stone-900 hover:underline">Service auto</a>
        <span aria-hidden="true">/</span>
        <span class="text-stone-900">{{ $cityName }}</span>
    </nav>

    <header class="space-y-2">
        <h1 class="text-3xl font-bold tracking-tight">Service auto în {{ $cityName }}</h1>
        <p class="text-sm text-stone-600">{{ $shops->total() }} ateliere listate. Ordinea este influențată de listările plătite, marcate ca atare.</p>
    </header>

    <div class="flex flex-wrap items-center gap-5 rounded-xl border border-stone-200 bg-white p-4 text-sm">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Specializare</span>
            <select wire:model.live="speciality">
                <option value="">Toate</option>
                @foreach($specialities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 pt-5">
            <input type="checkbox" wire:model.live="fitsOurParts">
            Montează piesele noastre
        </label>

        <label class="flex items-center gap-2 pt-5">
            <input type="checkbox" wire:model.live="openNow">
            Deschis acum
        </label>
    </div>

    @if($shops->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Niciun service din {{ $cityName }} nu corespunde filtrelor alese.
        </p>
    @else
        <div class="space-y-3">
            @foreach($shops as $shop)
                <x-storefront.shop-card :shop="$shop" />
            @endforeach
        </div>

        <div>{{ $shops->links() }}</div>
    @endif

    <p class="border-t border-stone-200 pt-6 text-sm text-stone-500">
        <a href="{{ route('storefront.services') }}" class="font-semibold text-stone-900 underline underline-offset-4">Vezi toate orașele</a>
    </p>
</div>
