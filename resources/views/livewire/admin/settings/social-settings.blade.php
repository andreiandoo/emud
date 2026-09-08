<form wire:submit="save" class="max-w-3xl">
    <x-admin.panel title="Rețele sociale" subtitle="Linkurile completate apar în footerul magazinului. Cele goale nu se afișează.">
        @if($saved)
            <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($networks as $key => $network)
                <label class="block" wire:key="social-{{ $key }}">
                    <span class="field-label">{{ $network['label'] }}</span>
                    <input type="url" wire:model="links.{{ $key }}" placeholder="https://{{ $network['host'] }}/...">
                    @error('links.'.$key) <span class="field-error">{{ $message }}</span> @enderror
                </label>
            @endforeach
        </div>

        <div class="border-t border-stone-100 pt-5">
            <button type="submit" class="btn-primary">Salvează</button>
        </div>
    </x-admin.panel>
</form>
