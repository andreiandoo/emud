<div>
    <x-admin.page-header title="Reguli de preț"
                         subtitle="Marja brută țintă pe costul aterizat, fără TVA. Regula cea mai specifică câștigă: brand, apoi furnizor, apoi categoria cea mai apropiată, apoi regula implicită." />

    @if(session('status'))
        <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <div class="overflow-hidden rounded-xl border bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-stone-50 text-xs uppercase text-stone-500">
                        <tr>
                            <th class="p-3">Se aplică la</th><th class="p-3 text-right">Marjă țintă</th>
                            <th class="p-3 text-right">Contribuție minimă</th><th class="p-3 text-right">Mișcare automată max.</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                    @foreach($rules as $row)
                        @php($rule = $row['rule'])
                        <tr @class(['align-top', 'opacity-50' => ! $rule->is_active]) wire:key="rule-{{ $rule->id }}">
                            <td class="p-3">
                                <div class="text-[11px] uppercase tracking-wider text-stone-500">{{ $rule->scope_type->label() }}</div>
                                <div class="font-medium text-stone-900">{{ $row['label'] }}</div>
                                @if($rule->notes)<div class="mt-1 text-xs text-stone-500">{{ $rule->notes }}</div>@endif
                            </td>
                            <td class="p-3 text-right font-semibold">{{ rtrim(rtrim((string) $rule->target_gross_margin_percent, '0'), '.') }}%</td>
                            <td class="p-3 text-right text-stone-600">{{ $rule->minimum_contribution_percent !== null ? rtrim(rtrim((string) $rule->minimum_contribution_percent, '0'), '.').'%' : 'moștenită' }}</td>
                            <td class="p-3 text-right text-stone-600">{{ $rule->max_auto_change_percent !== null ? rtrim(rtrim((string) $rule->max_auto_change_percent, '0'), '.').'%' : 'moștenită' }}</td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <button type="button" wire:click="edit({{ $rule->id }})" class="btn-ghost">Editează</button>
                                @unless($rule->scope_type === \App\Enums\PricingScope::Default)
                                    <button type="button" wire:click="toggle({{ $rule->id }})" class="btn-ghost">{{ $rule->is_active ? 'Oprește' : 'Pornește' }}</button>
                                    <button type="button" wire:click="delete({{ $rule->id }})" wire:confirm="Ștergi regula? Produsele trec la regula următoare ca specificitate." class="btn-ghost">Șterge</button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t p-3 text-xs text-stone-500">„Moștenită” înseamnă valoarea regulii implicite (azi {{ $defaults['minimum_contribution'] }}% contribuție, {{ $defaults['max_change'] }}% mișcare) dacă regula implicită nu stabilește altceva.</p>
        </div>

        <form wire:submit="save" class="card-padded space-y-4">
            <h2 class="font-bold">{{ $editing ? 'Editează regula' : 'Regulă nouă' }}</h2>

            <label class="block">
                <span class="field-label">Tip</span>
                <select wire:model.live="scopeType">
                    @foreach($scopes as $scope)<option value="{{ $scope->value }}">{{ $scope->label() }}</option>@endforeach
                </select>
            </label>

            @if($scopeType === 'category')
                <label class="block">
                    <span class="field-label">Categorie</span>
                    <select wire:model="scopeId">
                        <option value="">—</option>
                        @foreach($categories as $category)<option value="{{ $category->id }}">{{ str_repeat('— ', (int) $category->depth) }}{{ $category->name }}</option>@endforeach
                    </select>
                </label>
            @elseif($scopeType === 'brand')
                <label class="block">
                    <span class="field-label">Brand</span>
                    <select wire:model="scopeId"><option value="">—</option>@foreach($brands as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </label>
            @elseif($scopeType === 'supplier')
                <label class="block">
                    <span class="field-label">Furnizor</span>
                    <select wire:model="scopeId"><option value="">—</option>@foreach($suppliers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </label>
            @endif
            @error('scopeId') <span class="field-error">{{ $message }}</span> @enderror

            <label class="block">
                <span class="field-label">Marjă brută țintă (%)</span>
                <input wire:model="targetMargin" inputmode="decimal" placeholder="ex. 30">
                @error('targetMargin') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Contribuție minimă (%) — gol = moștenită</span>
                <input wire:model="minimumContribution" inputmode="decimal">
                @error('minimumContribution') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Mișcare automată maximă (%) — gol = moștenită</span>
                <input wire:model="maxChange" inputmode="decimal">
                @error('maxChange') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Notă</span>
                <input wire:model="notes">
            </label>

            <div class="flex gap-2">
                <button class="btn-primary">Salvează</button>
                @if($editing)<button type="button" wire:click="resetForm" class="btn-ghost">Renunță</button>@endif
            </div>
        </form>
    </div>
</div>
