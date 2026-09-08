<div>
    <x-admin.page-header title="Service auto" subtitle="Directorul de service-uri și pozițiile promovate plătit.">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary">Adaugă service</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-8 lg:grid-cols-[22rem_1fr]">
        <aside class="space-y-3">
            <label class="relative block">
                <span class="sr-only">Caută service</span>
                <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                <input wire:model.live.debounce.400ms="search" class="pl-9" placeholder="Caută după nume">
            </label>

            @if($shops->isEmpty())
                <x-admin.empty title="Niciun service" hint="Adaugă primul service din butonul de sus." />
            @else
                <ul class="space-y-1">
                    @foreach($shops as $shop)
                        <li wire:key="shop-{{ $shop->id }}">
                            <button type="button" wire:click="edit({{ $shop->id }})" @class([
                                'w-full rounded-lg px-3 py-2.5 text-left text-sm transition',
                                'bg-stone-900 text-white' => $editingId === $shop->id,
                                'hover:bg-stone-100' => $editingId !== $shop->id,
                            ])>
                                <span class="block truncate font-medium">{{ $shop->name }}</span>
                                <span class="block truncate text-xs opacity-70">
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
            @endif
        </aside>

        <form wire:submit="save">
            <x-admin.panel :title="$editingId ? 'Editează service-ul' : 'Service nou'"
                           subtitle="Datele de aici alimentează pagina publică din directorul de service-uri.">
                @if($saved)
                    <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="field-label">Denumire</span>
                        <input wire:model.live.debounce.500ms="name">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">Slug</span>
                        <input wire:model="slug">
                        @error('slug') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">Județ</span>
                        <input wire:model="county">
                        @error('county') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">Oraș</span>
                        <input wire:model="city">
                        @error('city') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="block">
                    <span class="field-label">Adresă</span>
                    <input wire:model="address">
                </label>

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="block">
                        <span class="field-label">Telefon</span>
                        <input wire:model="phone">
                    </label>
                    <label class="block">
                        <span class="field-label">Email</span>
                        <input type="email" wire:model="email">
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">Website</span>
                        <input type="url" wire:model="website" placeholder="https://…">
                        @error('website') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="block">
                    <span class="field-label">Specializări</span>
                    <input wire:model="specialities" placeholder="off-road, suspensie, diagnoză">
                    <span class="field-hint">Separate prin virgulă. Devin filtre în director.</span>
                </label>

                <label class="block">
                    <span class="field-label">Descriere (HTML)</span>
                    <textarea wire:model="description" rows="6" class="font-mono text-xs"></textarea>
                </label>

                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" wire:model="fits_parts_bought_here">
                    Montează piese cumpărate din magazinul nostru
                </label>

                <x-admin.section title="Promovare plătită">
                    {{-- Stated in the back office too, not only on the public page: the person
                         setting the tier is the one who needs to know it must be declared. --}}
                    <p class="text-xs text-stone-500">
                        Orice nivel plătit este afișat public ca atare și influențează ordinea în director.
                        Legea cere ca poziționarea plătită să fie declarată cititorului.
                    </p>

                    <div class="grid gap-4 rounded-xl bg-stone-50 p-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="field-label">Nivel</span>
                            <select wire:model.live="promotion_tier">
                                @foreach($tiers as $tier)
                                    <option value="{{ $tier->value }}">{{ $tier->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="field-label">Valabil până la</span>
                            <input type="date" wire:model="promoted_until" @disabled($promotion_tier === 'none')>
                            @error('promoted_until') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="field-label">Notă contract</span>
                            <input wire:model="promotion_notes">
                        </label>
                    </div>
                </x-admin.section>

                <div class="flex flex-wrap items-end gap-3 border-t border-stone-100 pt-5">
                    <label class="block w-40">
                        <span class="field-label">Stare</span>
                        <select wire:model="shopStatus">
                            <option value="draft">Ciornă</option>
                            <option value="published">Publicat</option>
                        </select>
                    </label>

                    <button type="submit" class="btn-primary">Salvează</button>

                    @if($editingId && $shopStatus === 'published')
                        <a href="{{ route('storefront.service', $slug) }}" target="_blank" rel="noopener" class="btn-secondary">Vezi public</a>
                    @endif
                </div>
            </x-admin.panel>
        </form>
    </div>
</div>
