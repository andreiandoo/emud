<div>
    <div class="mb-6"><h1 class="text-2xl font-bold">Categorii</h1><p class="text-stone-500">Taxonomie ierarhică inspirată din catalogul eMudding, extensibilă fără limită de nivel.</p></div>
    <input wire:model.live.debounce.300ms="search" placeholder="Caută o categorie" class="mb-4 w-full max-w-md rounded-lg border bg-white px-3 py-2">
    <div class="overflow-hidden rounded-xl border bg-white"><table class="w-full text-left text-sm"><thead class="bg-stone-50 text-stone-500"><tr><th class="p-3">Categorie</th><th class="p-3">Produse</th><th class="p-3">Filtre</th><th class="p-3">Activă</th></tr></thead><tbody>
    @foreach($categories as $category)<tr class="border-t"><td class="p-3"><span class="text-stone-300">{{ str_repeat('— ', $category->depth) }}</span><span class="font-medium">{{ $category->name }}</span><div class="ml-4 text-xs text-stone-400">{{ $category->full_path }}</div></td><td class="p-3">{{ $category->products_count }}</td><td class="p-3">{{ $category->attributes_count }}</td><td class="p-3"><button wire:click="toggle({{ $category->id }})" @class(['rounded-full px-3 py-1 text-xs font-semibold','bg-lime-100 text-lime-800'=>$category->is_active,'bg-stone-200 text-stone-600'=>!$category->is_active])>{{ $category->is_active ? 'Da' : 'Nu' }}</button></td></tr>@endforeach
    </tbody></table></div>
</div>
