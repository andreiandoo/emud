@props(['prefix' => '', 'company' => false, 'county' => ''])

{{-- The fields of one address, shared by the account page and checkout so the two can never ask
     for different things. `prefix` is the Livewire property the fields bind into ("billing"
     binds billing.city); empty for a component that keeps them flat. With `company`, the name
     fields give way to the firm's name, tax code and trade register number.

     The county is a list, so a town and its county can be matched to the workshops near them.
     The town is a field with suggestions from that county rather than a closed list: a village
     with no workshop in it is still somewhere a parcel can go.

     The autocomplete tokens name their section, so a browser fills the delivery address into
     delivery and the invoice address into invoice, not the same one twice. --}}
@php($key = fn (string $field): string => $prefix === '' ? $field : $prefix.'.'.$field)
@php($section = $prefix === 'billing' ? 'billing' : 'shipping')
@php($counties = \App\Directory\Localities::counties())
@php($towns = \App\Directory\Localities::forCounty($county))
@php($listId = 'localities-'.($prefix ?: 'address'))

<div class="grid gap-4">
    @if($company)
        <label class="block">
            <span class="field-label">Denumirea firmei</span>
            <input type="text" wire:model="{{ $key('company') }}" autocomplete="{{ $section }} organization">
            @error($key('company')) <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">Cod fiscal (CUI)</span>
                <input type="text" wire:model="{{ $key('vat_number') }}" placeholder="RO12345678" autocomplete="off" spellcheck="false" class="font-mono uppercase">
                @error($key('vat_number')) <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Nr. Reg. Com. <span class="font-normal normal-case tracking-normal opacity-70">(opțional)</span></span>
                <input type="text" wire:model="{{ $key('trade_register_number') }}" placeholder="J40/1234/2020" autocomplete="off" spellcheck="false" class="font-mono uppercase">
                @error($key('trade_register_number')) <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">Nume</span>
                <input type="text" wire:model="{{ $key('last_name') }}" autocomplete="{{ $section }} family-name">
                @error($key('last_name')) <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Prenume</span>
                <input type="text" wire:model="{{ $key('first_name') }}" autocomplete="{{ $section }} given-name">
                @error($key('first_name')) <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>
    @endif

    <label class="block">
        <span class="field-label">{{ $company ? 'Sediul social' : 'Adresă' }}</span>
        <input type="text" wire:model="{{ $key('line_1') }}" autocomplete="{{ $section }} street-address" placeholder="Strada, numărul, blocul, apartamentul">
        @error($key('line_1')) <span class="field-error">{{ $message }}</span> @enderror
    </label>

    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block">
            <span class="field-label">Județ</span>
            <select wire:model.live="{{ $key('county') }}" autocomplete="{{ $section }} address-level1">
                <option value="">Alege județul</option>
                {{-- A county saved before this was a list keeps showing as it was written. --}}
                @if($county !== '' && ! in_array($county, $counties, true))
                    <option value="{{ $county }}">{{ $county }}</option>
                @endif
                @foreach($counties as $countyName)
                    <option value="{{ $countyName }}">{{ $countyName }}</option>
                @endforeach
            </select>
            @error($key('county')) <span class="field-error">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="field-label">Localitate</span>
            <input type="text" list="{{ $listId }}" wire:model="{{ $key('city') }}" autocomplete="{{ $section }} address-level2"
                   placeholder="{{ $county === '' ? 'Alege întâi județul' : 'Scrie sau alege' }}">
            <datalist id="{{ $listId }}">
                @foreach($towns as $town)
                    <option value="{{ $town }}"></option>
                @endforeach
            </datalist>
            @error($key('city')) <span class="field-error">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="field-label">Cod poștal</span>
            <input type="text" wire:model="{{ $key('postal_code') }}" autocomplete="{{ $section }} postal-code" inputmode="numeric">
            @error($key('postal_code')) <span class="field-error">{{ $message }}</span> @enderror
        </label>
    </div>
</div>
