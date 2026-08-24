<div>
    <div class="mb-6"><h1 class="text-2xl font-bold">Filtre și atribute</h1><p class="text-stone-500">Definiții reutilizabile pe categorii, produse și variante.</p></div>
    <div class="overflow-hidden rounded-xl border bg-white"><table class="w-full text-left text-sm"><thead class="bg-stone-50 text-stone-500"><tr><th class="p-3">Atribut</th><th class="p-3">Cod</th><th class="p-3">Tip</th><th class="p-3">Unitate</th><th class="p-3">Categorii</th><th class="p-3">Opțiuni</th></tr></thead><tbody>
    @foreach($attributes as $attribute)<tr class="border-t"><td class="p-3 font-medium">{{ $attribute->name }}</td><td class="p-3 font-mono text-xs">{{ $attribute->code }}</td><td class="p-3">{{ $attribute->type }}</td><td class="p-3">{{ $attribute->unit ?? '—' }}</td><td class="p-3">{{ $attribute->categories_count }}</td><td class="p-3">{{ $attribute->options_count }}</td></tr>@endforeach
    </tbody></table></div>
</div>
