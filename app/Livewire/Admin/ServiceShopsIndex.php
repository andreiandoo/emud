<?php

namespace App\Livewire\Admin;

use App\Enums\ServiceLeadEventType;
use App\Models\ServiceShop;
use App\Models\ServiceShopLeadEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The directory as a list, with the numbers a paid listing is argued about from.
 */
#[Layout('layouts::admin')]
class ServiceShopsIndex extends Component
{
    use WithPagination;

    /** @var array<string, string> */
    public const TABS = [
        '' => 'Toate',
        'published' => 'Publicate',
        'draft' => 'Ciorne',
        'promoted' => 'Promovate',
        'registry' => 'Din registru',
        'manual' => 'Adăugate manual',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $tab = '';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function togglePublished(int $shopId): void
    {
        $shop = ServiceShop::query()->findOrFail($shopId);

        $shop->update([
            'status' => $shop->status === 'published' ? 'draft' : 'published',
            // Published or taken down by a person: the registry sync must not reverse it.
            'registry_locked' => $shop->workshop_id === null
                ? $shop->registry_locked
                : array_values(array_unique([...$shop->lockedFields(), 'status'])),
        ]);
    }

    public function render()
    {
        $shops = $this->query()
            ->withCount(['services', 'appointments'])
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.admin.service-shops-index', [
            'shops' => $shops,
            'tabs' => self::TABS,
            'counts' => $this->counts(),
            'leads' => $this->leadsThisMonth($shops->pluck('id')),
            'leadTypes' => ServiceLeadEventType::cases(),
        ]);
    }

    private function query(): Builder
    {
        return ServiceShop::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('lower(name) like ?', [$term])
                    ->orWhereRaw('lower(city) like ?', [$term]));
            })
            ->when($this->tab === 'published', fn (Builder $q) => $q->where('status', 'published'))
            ->when($this->tab === 'draft', fn (Builder $q) => $q->where('status', 'draft'))
            // Expired promotions are not promotions, so the filter uses the same cut-off the
            // public ordering does rather than the raw column.
            ->when($this->tab === 'promoted', fn (Builder $q) => $q->where('promotion_tier', '!=', 'none')
                ->where(fn (Builder $inner) => $inner->whereNull('promoted_until')->orWhere('promoted_until', '>=', now()->toDateString())))
            ->when($this->tab === 'registry', fn (Builder $q) => $q->whereNotNull('workshop_id'))
            ->when($this->tab === 'manual', fn (Builder $q) => $q->whereNull('workshop_id'));
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $byStatus = ServiceShop::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            '' => (int) $byStatus->sum(),
            'published' => (int) $byStatus->get('published', 0),
            'draft' => (int) $byStatus->get('draft', 0),
            'promoted' => ServiceShop::query()
                ->where('promotion_tier', '!=', 'none')
                ->where(fn (Builder $q) => $q->whereNull('promoted_until')->orWhere('promoted_until', '>=', now()->toDateString()))
                ->count(),
            'registry' => ServiceShop::query()->whereNotNull('workshop_id')->count(),
            'manual' => ServiceShop::query()->whereNull('workshop_id')->count(),
        ];
    }

    /**
     * Leads since the start of the month, per workshop and per type. One grouped query rather
     * than a count per row, because this column is on every row of the list.
     *
     * @param  Collection<int, int>  $shopIds
     * @return array<int, array<string, int>>
     */
    private function leadsThisMonth(Collection $shopIds): array
    {
        if ($shopIds->isEmpty()) {
            return [];
        }

        return ServiceShopLeadEvent::query()
            ->selectRaw('service_shop_id, type, count(*) as total')
            ->whereIn('service_shop_id', $shopIds)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->groupBy('service_shop_id', 'type')
            ->get()
            ->groupBy('service_shop_id')
            // Keyed by the enum's value, not the enum: the cast hands back an object, and an
            // object cannot be an array key.
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn ($row) => [$row->type->value => (int) $row->total])->all())
            ->all();
    }
}
