<div>
    <div class="mb-6"><h1 class="text-2xl font-bold">Panou general</h1><p class="text-stone-500">Starea catalogului și a automatizărilor.</p></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($metrics as $label => $value)
            <div class="rounded-xl border border-stone-200 bg-white p-5"><div class="text-sm text-stone-500">{{ $label }}</div><div class="mt-2 text-3xl font-black">{{ number_format($value, 0, ',', '.') }}</div></div>
        @endforeach
    </div>
    <div class="mt-8 overflow-hidden rounded-xl border border-stone-200 bg-white">
        <div class="border-b p-5"><h2 class="font-bold">Ultimele sincronizări</h2></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-stone-50 text-stone-500"><tr><th class="p-3">Furnizor</th><th class="p-3">Mod</th><th class="p-3">Status</th><th class="p-3">Procesate</th><th class="p-3">Pornită</th></tr></thead><tbody>
        @forelse ($recentRuns as $run)<tr class="border-t"><td class="p-3 font-medium">{{ $run->supplier->name }}</td><td class="p-3">{{ $run->mode }}</td><td class="p-3">{{ $run->status->value }}</td><td class="p-3">{{ $run->processed }}</td><td class="p-3">{{ $run->created_at->diffForHumans() }}</td></tr>@empty<tr><td colspan="5" class="p-6 text-center text-stone-500">Nicio sincronizare încă.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>
