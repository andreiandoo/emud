<div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
    <aside class="space-y-3">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-bold">Service auto</h1>
            <button wire:click="create" class="rounded-lg bg-stone-900 px-3 py-1.5 text-sm font-semibold text-white">Adaugă</button>
        </div>

        <input wire:model.live.debounce.400ms="search" placeholder="Caută după nume" class="w-full rounded-lg border-stone-300 text-sm">

        <ul class="space-y-1">
            @foreach($shops as $shop)
                <li>
                    <button wire:click="edit({{ $shop->id }})" @class([
                        'w-full rounded-lg px-3 py-2 text-left text-sm',
                        'bg-stone-900 text-white' => $editingId === $shop->id,
                        'hover:bg-stone-100' => $editingId !== $shop->id,
                    ])>
                        <span class="block truncate font-medium">{{ $shop->name }}</span>
                        <span class="block text-xs opacity-70">
                            {{ $shop->city }}, {{ $shop->county }}
                            @if($shop->effectiveTier()->isPaid()) · {{ $shop->effectiveTier()->label() }} @endif
                            @if($shop->status !== 'published') · ciornă @endif
                            @if($shop->promotion_tier->isPaid() && ! $shop->isPromoted()) · promovare expirată @endif
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>

        <div>{{ $shops->links() }}</div>
    </aside>

    <form wire:submit="save" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="text-lg font-bold">{{ $editingId ? 'Editează service-ul' : 'Service nou' }}</h2>
            @if($saved)<span class="text-sm font-semibold text-lime-700">{{ $saved }}</span>@endif
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Denumire</span>
                <input wire:model.live.debounce.500ms="name" class="w-full rounded-lg border-stone-300 text-sm">
                @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Slug</span>
                <input wire:model="slug" class="w-full rounded-lg border-stone-300 text-sm">
                @error('slug') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Județ</span>
                <input wire:model="county" class="w-full rounded-lg border-stone-300 text-sm">
                @error('county') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Oraș</span>
                <input wire:model="city" class="w-full rounded-lg border-stone-300 text-sm">
                @error('city') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Adresă</span>
            <input wire:model="address" class="w-full rounded-lg border-stone-300 text-sm">
        </label>

        <div class="grid gap-3 sm:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Telefon</span>
                <input wire:model="phone" class="w-full rounded-lg border-stone-300 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Email</span>
                <input wire:model="email" class="w-full rounded-lg border-stone-300 text-sm">
                @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Website</span>
                <input wire:model="website" placeholder="https://…" class="w-full rounded-lg border-stone-300 text-sm">
                @error('website') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Specializări</span>
            <input wire:model="specialities" placeholder="off-road, suspensie, diagnoză" class="w-full rounded-lg border-stone-300 text-sm">
            <span class="mt-1 block text-xs text-stone-500">Separate prin virgulă. Devin filtre în director.</span>
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Descriere (HTML)</span>
            <textarea wire:model="description" rows="6" class="w-full rounded-lg border-stone-300 font-mono text-xs"></textarea>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="fits_parts_bought_here" class="rounded border-stone-300">
            Montează piese cumpărate din magazinul nostru
        </label>

        <div class="rounded-lg border border-stone-200 bg-stone-50 p-4">
            <h3 class="mb-1 text-sm font-bold">Promovare plătită</h3>
            <p class="mb-3 text-xs text-stone-600">
                Orice nivel plătit este afișat public ca atare și influențează ordinea în director.
                Legea cere ca poziționarea plătită să fie declarată cititorului.
            </p>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Nivel</span>
                    <select wire:model.live="promotion_tier" class="w-full rounded-lg border-stone-300 text-sm">
                        @foreach($tiers as $tier)<option value="{{ $tier->value }}">{{ $tier->label() }}</option>@endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Valabil până la</span>
                    <input type="date" wire:model="promoted_until" @disabled($promotion_tier === 'none') class="w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100">
                    @error('promoted_until') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Notă contract</span>
                    <input wire:model="promotion_notes" class="w-full rounded-lg border-stone-300 text-sm">
                </label>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Stare</span>
                <select wire:model="shopStatus" class="rounded-lg border-stone-300 text-sm">
                    <option value="draft">Ciornă</option>
                    <option value="published">Publicat</option>
                </select>
            </label>

            <button type="submit" class="mt-5 rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-stone-700">Salvează</button>

            @if($editingId && $shopStatus === 'published')
                <a href="{{ route('storefront.service', $slug) }}" target="_blank" class="mt-5 text-sm underline">Vezi public</a>
            @endif
        </div>
    </form>
</div>
