{{-- Who the invoice is made out to, bound to billingType on the Livewire component around it.
     Radios under the pills, so it is one choice to a keyboard and a screen reader rather than
     two unrelated buttons. --}}
<div class="flex shrink-0 rounded-full border border-line2 p-[3px] text-[13px]" role="radiogroup" aria-label="Factura se emite pe">
    @foreach(['person' => 'Persoană fizică', 'company' => 'Persoană juridică'] as $value => $label)
        <label class="cursor-pointer rounded-full px-3.5 py-1.5 font-medium text-ink2 transition has-[:checked]:bg-ink has-[:checked]:text-light has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-signal">
            <input type="radio" wire:model.live="billingType" value="{{ $value }}" class="sr-only">
            {{ $label }}
        </label>
    @endforeach
</div>
