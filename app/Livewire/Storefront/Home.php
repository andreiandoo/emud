<?php

namespace App\Livewire\Storefront;

use App\Models\Article;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ServiceShop;
use App\Storefront\CatalogMetrics;
use App\Storefront\CategoryMenu;
use App\Storefront\CollectionShowcase;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The front page.
 *
 * Everything it shows is what the shop actually has: collections that exist, products that
 * sold, reviews that were published, cities that have a partner service. Sections whose source
 * is empty are left out rather than filled with placeholders, so a young catalogue looks small
 * rather than fake.
 */
#[Layout('layouts::storefront', ['fullWidth' => true, 'overlayHeader' => true])]
class Home extends Component
{
    /**
     * Terrain types, each pointing at the categories that matter on it. Paths that do not exist
     * in the catalogue are dropped at render time, so renaming a category removes a chip rather
     * than leaving a link to a missing page.
     */
    private const SURFACES = [
        [
            'key' => 'mud',
            'name' => 'Noroi',
            'scene' => 'mud',
            'seed' => 3,
            'pressure' => '1,2–1,6 bar',
            'text' => 'Anvelope MT, snorkel și un troliu care te scoate de acolo când te-ai înfundat.',
            'paths' => [
                'anvelope/anvelope-off-road',
                'accesorii-interior-exterior/accesorii-exterior/snorkele',
                'trolii-si-recuperare/trolii-electrice',
                'trolii-si-recuperare/accesorii-recuperare/sufe-cinetice',
            ],
        ],
        [
            'key' => 'rock',
            'name' => 'Stâncă',
            'scene' => 'steel',
            'seed' => 19,
            'pressure' => '1,0–1,4 bar',
            'text' => 'Scuturi, praguri întărite și suspensie cu articulație mare, pentru pașii grei.',
            'paths' => [
                'accesorii-interior-exterior/accesorii-exterior/scuturi-metalice',
                'accesorii-interior-exterior/accesorii-exterior/praguri-laterale',
                'suspensie-directie/kit-uri-de-inaltare',
                'transmisie/diferentiale-blocabile',
            ],
        ],
        [
            'key' => 'sand',
            'name' => 'Nisip',
            'scene' => 'sand',
            'seed' => 27,
            'pressure' => '0,8–1,1 bar',
            'text' => 'Compresoare, plăci antiderapare și filtre de aer care nu se înfundă la prima dună.',
            'paths' => [
                'trolii-si-recuperare/compresoare',
                'trolii-si-recuperare/accesorii-recuperare/placi-antiderapare',
                'piese-de-schimb/filtre-aer',
            ],
        ],
        [
            'key' => 'snow',
            'name' => 'Zăpadă',
            'scene' => 'snow',
            'seed' => 44,
            'pressure' => '1,6–1,9 bar',
            'text' => 'Anvelope, proiectoare și echipament de recuperare pentru drumurile închise iarna.',
            'paths' => [
                'anvelope/anvelope-de-strada',
                'iluminare/proiectoare',
                'trolii-si-recuperare/accesorii-recuperare/hi-lift',
                'trolii-si-recuperare/accesorii-recuperare',
            ],
        ],
    ];

    /** The stops the trail animation walks through: what helps at each point of a day out. */
    private const STOPS = [
        [
            't' => 0.0,
            'title' => 'Plecarea',
            'text' => 'Presiunea potrivită terenului și un compresor, ca s-o refaci la asfalt.',
            'paths' => ['trolii-si-recuperare/compresoare'],
        ],
        [
            't' => 0.29,
            'title' => 'Vadul',
            'text' => 'Apa intră pe admisie înainte să treacă de praguri. Un snorkel mută priza de aer sus.',
            'paths' => ['accesorii-interior-exterior/accesorii-exterior/snorkele'],
        ],
        [
            't' => 0.56,
            'title' => 'Urcarea',
            'text' => 'Pantă, pietriș, o roată în aer: aici contează blocajul de diferențial și scutul de sub motor.',
            'paths' => ['transmisie/diferentiale-blocabile', 'accesorii-interior-exterior/accesorii-exterior/scuturi-metalice'],
        ],
        [
            't' => 0.86,
            'title' => 'Tabăra',
            'text' => 'Cort pe plafon, lumină pentru seară și energie pentru frigider.',
            'paths' => ['camping-si-outdoor/corturi-de-acoperis-auto', 'iluminare/proiectoare'],
        ],
    ];

    #[On('vehicle-changed')]
    public function refreshVehicle(): void
    {
        // The picker owns the selection; the page re-renders so the vehicle-aware sections
        // reflect it without a full navigation.
    }

    public function render(
        VehicleContext $context,
        FitmentMatcher $matcher,
        CatalogMetrics $metrics,
        CollectionShowcase $showcase,
        CategoryMenu $menu,
    ) {
        $vehicle = $context->current();
        $links = $this->categoryLinks();

        return view('livewire.storefront.home', [
            'vehicle' => $vehicle,
            'collections' => $showcase->featured(12),
            'bestSellers' => $this->bestSellers($vehicle, $matcher),
            'verdicts' => fn (Product $product) => $vehicle === null ? null : $matcher->verdictFor($product, $vehicle),
            'metrics' => collect($metrics->snapshot())
                ->only(['makes', 'models', 'configurations', 'products'])
                ->filter(fn (array $metric): bool => $metric['value'] > 0),
            'categories' => $menu->tree(),
            'surfaces' => $this->withLinks(self::SURFACES, $links),
            'stops' => $this->withLinks(self::STOPS, $links),
            'reviews' => Review::query()->published()->where('is_featured', true)->ordered()->limit(9)->get(),
            'articles' => Article::query()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->with('category')
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
            'cities' => ServiceShop::query()
                ->published()
                ->whereNotNull('city_slug')
                ->selectRaw('city, city_slug, count(*) as shops')
                ->groupBy('city', 'city_slug')
                ->orderByDesc('shops')
                ->limit(6)
                ->get(),
            'serviceCount' => ServiceShop::query()->published()->count(),
        ]);
    }

    /**
     * What sells, filtered to the visitor's car when they have chosen one. Sales come first, in
     * order of units sold; a young shop with few orders is topped up with featured products and
     * then the newest, so the rail is never a single lonely card.
     *
     * @return Collection<int, Product>
     */
    private function bestSellers(?SelectedVehicle $vehicle, FitmentMatcher $matcher): Collection
    {
        $query = Product::query()->active()->with(['brand', 'media', 'fitments', 'variants']);

        if ($vehicle !== null) {
            $matcher->scopeForVehicle($query, $vehicle);
        }

        $sold = OrderItem::query()
            ->whereNotNull('product_id')
            ->selectRaw('product_id, sum(quantity) as sold')
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->limit(40)
            ->pluck('product_id')
            ->all();

        $rank = array_flip($sold);

        $ranked = $sold === []
            ? collect()
            : (clone $query)->whereIn('id', $sold)->get()->sortBy(fn (Product $product): int => $rank[$product->id])->take(12)->values();

        $missing = 12 - $ranked->count();

        if ($missing <= 0) {
            return $ranked;
        }

        $filler = (clone $query)
            ->whereNotIn('id', $ranked->pluck('id'))
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit($missing)
            ->get();

        return $ranked->concat($filler)->values();
    }

    /** @return Collection<string, Category> keyed by full_path */
    private function categoryLinks(): Collection
    {
        $paths = collect([...self::SURFACES, ...self::STOPS])->pluck('paths')->flatten()->unique()->values();

        return Category::query()
            ->where('is_active', true)
            ->whereIn('full_path', $paths)
            ->get(['id', 'name', 'full_path'])
            ->keyBy('full_path');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  Collection<string, Category>  $links
     * @return list<array<string, mixed>>
     */
    private function withLinks(array $items, Collection $links): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                ...$item,
                'links' => collect($item['paths'])
                    ->map(fn (string $path): ?Category => $links->get($path))
                    ->filter()
                    ->values(),
            ])
            ->all();
    }
}
