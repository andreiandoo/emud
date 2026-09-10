<div>
    <x-admin.page-header title="Recenzii"
                         subtitle="Ce au trimis clienții după montaj: text, poză, video și contul lor.">
        <x-slot:actions>
            <a href="{{ route('admin.reviews.create') }}" class="btn-primary">Recenzie nouă</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($flash !== '')
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-900">{{ $flash }}</p>
    @endif

    <div class="mb-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Caută după nume sau text…" class="max-w-xs">
    </div>

    <x-admin.tabs :tabs="$tabs" :current="$tab" :counts="$counts" />

    @if($reviews->isEmpty())
        <x-admin.empty title="Nicio recenzie"
                       hint="Adaugă prima recenzie primită de la un client.">
            <a href="{{ route('admin.reviews.create') }}" class="btn-primary">Recenzie nouă</a>
        </x-admin.empty>
    @else
        <div class="table-flat">
            <table>
                <thead>
                    <tr>
                        <th class="w-14"><span class="sr-only">Media</span></th>
                        <th>Client</th>
                        <th>Recenzie</th>
                        <th>Despre</th>
                        <th>Notă</th>
                        <th>Publicată</th>
                        <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reviews as $review)
                        <tr wire:key="review-{{ $review->id }}">
                            <td>
                                @if($review->imageUrl())
                                    <img src="{{ $review->imageUrl() }}" alt="" class="h-10 w-10 rounded-lg object-cover">
                                @elseif($review->video_url)
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-stone-100 text-xs text-stone-500">▶</span>
                                @else
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-stone-100 text-stone-400">
                                        <x-admin.icon name="star" />
                                    </span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.reviews.edit', $review) }}" class="font-medium text-stone-900 hover:underline">
                                    {{ $review->reviewer_name }}
                                </a>
                                @if($review->reviewer_instagram || $review->reviewer_facebook)
                                    <span class="block text-xs text-stone-400">
                                        {{ collect([$review->reviewer_instagram ? 'IG' : null, $review->reviewer_facebook ? 'FB' : null])->filter()->implode(' · ') }}
                                    </span>
                                @endif
                            </td>
                            <td class="max-w-sm">
                                <span class="block truncate text-stone-800">{{ $review->title ?: Str::limit($review->body, 60) }}</span>
                            </td>
                            <td class="text-stone-600">
                                {{ $review->product?->name ?? $review->vehicleLabel() ?? '—' }}
                            </td>
                            <td class="tabular-nums text-stone-600">{{ $review->rating ? $review->rating.'/5' : '—' }}</td>
                            <td>
                                <button type="button" wire:click="togglePublished({{ $review->id }})">
                                    <x-admin.status :label="$review->status === 'published' ? 'Publicată' : 'Ciornă'"
                                                    :tone="$review->status === 'published' ? 'positive' : 'neutral'" />
                                </button>
                                @if($review->is_featured)
                                    <span class="ml-1 pill-info">evidențiată</span>
                                @endif
                            </td>
                            <td>
                                <x-admin.row-actions>
                                    <x-admin.row-action href="{{ route('admin.reviews.edit', $review) }}">Editează</x-admin.row-action>
                                    <x-admin.row-action wire:click="toggleFeatured({{ $review->id }})">
                                        {{ $review->is_featured ? 'Scoate din evidențiate' : 'Evidențiază' }}
                                    </x-admin.row-action>
                                    <x-admin.row-action tone="danger" wire:click="delete({{ $review->id }})"
                                                        wire:confirm="Ștergi recenzia definitiv?">Șterge</x-admin.row-action>
                                </x-admin.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $reviews->links() }}</div>
    @endif
</div>
