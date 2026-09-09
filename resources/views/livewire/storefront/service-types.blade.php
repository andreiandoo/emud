<div class="space-y-8">
    <x-seo title="Lucrări și servicii auto"
           description="Ce înseamnă fiecare lucrare la mașină, cât durează, cât costă orientativ și ce piese folosește." />

    <header class="space-y-2">
        <h1 class="text-3xl font-bold tracking-tight">Lucrări și servicii auto</h1>
        <p class="text-sm text-stone-600">
            Ce presupune fiecare lucrare, cine o face și ce piese cere.
        </p>
    </header>

    @forelse($categories as $category)
        @continue($category->services->isEmpty())

        <section class="space-y-3">
            <h2 class="flex items-center gap-2 text-lg font-bold tracking-tight">
                <x-storefront.icon :name="$category->icon ?: 'tool'" class="h-5 w-5 text-stone-400" />
                {{ $category->name }}
            </h2>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($category->services as $service)
                    <a href="{{ route('storefront.service-type', $service->slug) }}"
                       class="rounded-xl border border-stone-200 bg-white p-4 transition hover:border-stone-900">
                        <span class="block font-medium text-stone-900">{{ $service->name }}</span>
                        <span class="mt-0.5 block text-xs text-stone-500">
                            {{ $service->shops_count }} {{ $service->shops_count === 1 ? 'service' : 'service-uri' }}
                            @if($service->typical_duration_minutes) · ~{{ $service->typical_duration_minutes }} min @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Nu am publicat încă lista de lucrări.
        </p>
    @endforelse
</div>
