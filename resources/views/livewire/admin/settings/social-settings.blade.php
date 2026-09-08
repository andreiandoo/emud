<form wire:submit="save" class="card-padded max-w-3xl space-y-5">
    @if($saved)
        <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
    @endif

    <p class="text-sm text-stone-600">
        Linkurile completate apar în footerul magazinului. Cele goale nu se afișează.
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($networks as $key => $network)
            <label class="block">
                <span class="field-label">{{ $network['label'] }}</span>
                <input type="url" wire:model="links.{{ $key }}" placeholder="https://{{ $network['host'] }}/...">
                @error('links.'.$key) <span class="field-error">{{ $message }}</span> @enderror
            </label>
        @endforeach
    </div>

    <button type="submit" class="btn-primary">Salvează</button>
</form>
