<div>
    <div class="mb-6"><h1 class="text-2xl font-semibold tracking-tight">Sincronizări</h1><p class="text-stone-500">Jurnal complet pentru fiecare rulare a feedurilor, cu rândurile care au eșuat.</p></div>

    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="status" class="rounded-lg border bg-white px-3 py-2"><option value="">Toate statusurile</option>@foreach(\App\Enums\SyncStatus::cases() as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</select>
        <select wire:model.live="mode" class="rounded-lg border bg-white px-3 py-2"><option value="">Toate modurile</option>@foreach(['catalog','prices','stock'] as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-stone-50 text-stone-500">
                    <tr>
                        <th class="p-3">Furnizor</th><th class="p-3">Mod</th><th class="p-3">Status</th>
                        <th class="p-3 text-right">Primite</th><th class="p-3 text-right">Noi</th><th class="p-3 text-right">Actualizate</th>
                        <th class="p-3 text-right">Respinse</th><th class="p-3 text-right">Eșuate</th><th class="p-3 text-right">Retrase</th>
                        <th class="p-3">Data</th><th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($runs as $run)
                    <tr class="border-t">
                        <td class="p-3 font-medium">{{ $run->supplier->name }}</td>
                        <td class="p-3">{{ $run->mode }}</td>
                        <td class="p-3">
                            <span @class([
                                'rounded px-2 py-0.5 text-[11px]',
                                'bg-lime-100 text-lime-900' => $run->status->isHealthy(),
                                'bg-amber-100 text-amber-900' => $run->status->needsAttention() && $run->status !== \App\Enums\SyncStatus::Failed,
                                'bg-red-100 text-red-900' => $run->status === \App\Enums\SyncStatus::Failed,
                                'bg-stone-100 text-stone-600' => ! $run->status->isHealthy() && ! $run->status->needsAttention(),
                            ])>{{ $run->status->label() }}</span>
                        </td>
                        <td class="p-3 text-right">{{ $run->received_count }}</td>
                        <td class="p-3 text-right">{{ $run->created_count }}</td>
                        <td class="p-3 text-right">{{ $run->updated_count }}</td>
                        <td @class(['p-3 text-right', 'font-bold text-amber-700' => $run->rejected_count > 0])>{{ $run->rejected_count }}</td>
                        <td @class(['p-3 text-right', 'font-bold text-red-700' => $run->failed_count > 0])>{{ $run->failed_count }}</td>
                        <td class="p-3 text-right">{{ $run->retired_count }}</td>
                        <td class="p-3 whitespace-nowrap">{{ $run->created_at->format('d.m.Y H:i') }}</td>
                        <td class="p-3 text-right">
                            @if($run->errors_count > 0)
                                <button wire:click="inspect({{ $run->id }})" class="rounded border px-2 py-1 text-xs hover:bg-stone-50">{{ $inspecting === $run->id ? 'Ascunde' : "Erori ({$run->errors_count})" }}</button>
                            @endif
                        </td>
                    </tr>
                    @if($inspecting === $run->id)
                        <tr class="border-t bg-stone-50">
                            <td colspan="11" class="p-4">
                                @if($run->status === \App\Enums\SyncStatus::AbortedGuard)
                                    <div class="mb-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">Rularea a fost oprită de gardă: feedul a returnat mult mai puține rânduri decât ultima rulare reușită. Datele importate au fost păstrate, dar niciun produs nu a fost retras și furnizorul nu a fost marcat ca sincronizat cu succes.</div>
                                @endif
                                <table class="w-full text-left text-xs">
                                    <thead class="text-stone-500"><tr><th class="p-2">Tip</th><th class="p-2">Identificator</th><th class="p-2">Mesaj</th><th class="p-2">Payload</th></tr></thead>
                                    <tbody>
                                    @foreach($errors as $error)
                                        <tr class="border-t border-stone-200 align-top">
                                            <td class="p-2 whitespace-nowrap">{{ $error->error_type->label() }}</td>
                                            <td class="p-2 font-mono">{{ $error->external_identifier ?? '—' }}</td>
                                            <td class="p-2">{{ $error->message }}</td>
                                            <td class="p-2"><pre class="max-h-32 overflow-auto rounded bg-white p-2 font-mono text-[10px]">{{ $error->raw_payload ? json_encode($error->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre></td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                                @if($run->errors_count > $errors->count())
                                    <p class="mt-2 text-xs text-stone-500">Se afișează primele {{ $errors->count() }} din {{ $run->errors_count }} erori.</p>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="11" class="p-8 text-center text-stone-500">Nicio rulare.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t p-4">{{ $runs->links() }}</div>
    </div>
</div>
