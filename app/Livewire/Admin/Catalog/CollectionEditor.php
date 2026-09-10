<?php

namespace App\Livewire\Admin\Catalog;

use App\Catalog\CollectionMatcher;
use App\Models\Product;
use App\Models\VehicleCollection;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\CollectionShowcase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * One collection, on tabs.
 *
 * Held by id rather than as a typed VehicleCollection property: a route parameter with the same
 * name as a typed model property makes Laravel resolve the record before mount() runs, which
 * bypasses whatever scoping mount() intended to apply.
 */
#[Layout('layouts::admin')]
class CollectionEditor extends Component
{
    use WithFileUploads;

    /** @var array<string, string> */
    public const TABS = [
        'general' => 'General',
        'media' => 'Imagini & video',
        'seo' => 'SEO',
        'products' => 'Produse',
    ];

    /** Which uploaded file lands in which column. */
    private const UPLOADS = [
        'squareImage' => 'square_image_path',
        'wideImage' => 'wide_image_path',
        'garageImage' => 'garage_image_path',
        'ogImage' => 'og_image_path',
    ];

    public string $tab = 'general';

    public ?int $collectionId = null;

    public string $name = '';

    public string $slug = '';

    public string $subtitle = '';

    public string $description = '';

    public string $makeId = '';

    public string $modelId = '';

    public string $generationId = '';

    public string $yearFrom = '';

    public string $yearTo = '';

    public string $videoUrl = '';

    public bool $isActive = true;

    public bool $isFeatured = false;

    public string $position = '0';

    public string $seoTitle = '';

    public string $seoDescription = '';

    public bool $robotsIndex = true;

    public bool $robotsFollow = true;

    public mixed $squareImage = null;

    public mixed $wideImage = null;

    public mixed $garageImage = null;

    public mixed $ogImage = null;

    public string $productSearch = '';

    public string $saved = '';

    /** @var array<string, string|null> */
    public array $images = [
        'square_image_path' => null,
        'wide_image_path' => null,
        'garage_image_path' => null,
        'og_image_path' => null,
    ];

    public function mount(?VehicleCollection $collection = null): void
    {
        if (! $collection?->exists) {
            return;
        }

        $this->fill([
            'collectionId' => $collection->id,
            'name' => (string) $collection->name,
            'slug' => (string) $collection->slug,
            'subtitle' => (string) $collection->subtitle,
            'description' => (string) $collection->description,
            'makeId' => (string) ($collection->make_id ?? ''),
            'modelId' => (string) ($collection->model_id ?? ''),
            'generationId' => (string) ($collection->generation_id ?? ''),
            'yearFrom' => (string) ($collection->year_from ?? ''),
            'yearTo' => (string) ($collection->year_to ?? ''),
            'videoUrl' => (string) $collection->video_url,
            'isActive' => (bool) $collection->is_active,
            'isFeatured' => (bool) $collection->is_featured,
            'position' => (string) $collection->position,
            'seoTitle' => (string) $collection->seo_title,
            'seoDescription' => (string) $collection->seo_description,
            'robotsIndex' => (bool) $collection->robots_index,
            'robotsFollow' => (bool) $collection->robots_follow,
        ]);

        $this->images = [
            'square_image_path' => $collection->square_image_path,
            'wide_image_path' => $collection->wide_image_path,
            'garage_image_path' => $collection->garage_image_path,
            'og_image_path' => $collection->og_image_path,
        ];
    }

    public function updatedName(string $value): void
    {
        // Only while the slug is still untouched and the collection is new: renaming a published
        // collection must not silently change a URL that is already linked to and indexed.
        if ($this->collectionId === null && $this->slug === '') {
            $this->slug = Str::slug($value);
        }
    }

    /** Picking a different make invalidates the model under it, and the same for generations. */
    public function updatedMakeId(): void
    {
        $this->modelId = '';
        $this->generationId = '';
    }

    public function updatedModelId(): void
    {
        $this->generationId = '';
    }

    public function save(CollectionMatcher $matcher): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/', Rule::unique('vehicle_collections', 'slug')->ignore($this->collectionId)],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'makeId' => ['nullable', 'integer', Rule::exists('vehicle_makes', 'id')],
            'modelId' => ['nullable', 'integer', Rule::exists('vehicle_models', 'id')],
            'generationId' => ['nullable', 'integer', Rule::exists('vehicle_generations', 'id')],
            'yearFrom' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'yearTo' => ['nullable', 'integer', 'min:1900', 'max:2100', 'gte:yearFrom'],
            'videoUrl' => ['nullable', 'url', 'max:2048'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'seoTitle' => ['nullable', 'string', 'max:180'],
            'seoDescription' => ['nullable', 'string', 'max:320'],
            'squareImage' => ['nullable', 'image', 'max:4096'],
            'wideImage' => ['nullable', 'image', 'max:6144'],
            'garageImage' => ['nullable', 'image', 'max:4096'],
            'ogImage' => ['nullable', 'image', 'max:4096'],
        ], [], [
            'name' => 'numele colecției',
            'slug' => 'adresa (slug)',
            'yearTo' => 'anul de sfârșit',
            'videoUrl' => 'linkul video',
        ]);

        $collection = $this->collectionId === null
            ? new VehicleCollection
            : VehicleCollection::query()->findOrFail($this->collectionId);

        $collection->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'subtitle' => $data['subtitle'] ?: null,
            'description' => $data['description'] ?: null,
            'make_id' => $data['makeId'] ?: null,
            'model_id' => $data['modelId'] ?: null,
            'generation_id' => $data['generationId'] ?: null,
            'year_from' => $data['yearFrom'] ?: null,
            'year_to' => $data['yearTo'] ?: null,
            'video_url' => $data['videoUrl'] ?: null,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
            'position' => (int) ($data['position'] ?: 0),
            'seo_title' => $data['seoTitle'] ?: null,
            'seo_description' => $data['seoDescription'] ?: null,
            'robots_index' => $this->robotsIndex,
            'robots_follow' => $this->robotsFollow,
            ...$this->storeUploads(),
        ])->save();

        $this->collectionId = $collection->id;
        $this->images = [
            'square_image_path' => $collection->square_image_path,
            'wide_image_path' => $collection->wide_image_path,
            'garage_image_path' => $collection->garage_image_path,
            'og_image_path' => $collection->og_image_path,
        ];
        $this->reset(['squareImage', 'wideImage', 'garageImage', 'ogImage']);

        // The vehicle this collection points at is what decides which products belong to it, so
        // the matcher's cached index is stale the moment it is saved.
        $matcher->forget();
        CollectionShowcase::forget();

        $this->saved = 'Colecția a fost salvată.';
    }

    /** Removes one image without touching the rest of the form. */
    public function removeImage(string $field): void
    {
        if (! array_key_exists($field, $this->images)) {
            return;
        }

        $this->images[$field] = null;

        if ($this->collectionId !== null) {
            VehicleCollection::query()->whereKey($this->collectionId)->update([$field => null]);
            CollectionShowcase::forget();
        }
    }

    /** Re-derives this collection's automatic membership from the fitments already in the catalogue. */
    public function rebuildProducts(CollectionMatcher $matcher): void
    {
        if ($this->collectionId === null) {
            return;
        }

        $matcher->forget();
        $collection = VehicleCollection::query()->findOrFail($this->collectionId);

        // Only products whose fitments touch this collection's vehicle, plus whatever is already
        // attached so a stale link can be dropped. Walking the whole catalogue for one collection
        // would be a two-hundred-thousand-row scan behind a button an operator presses casually.
        $candidates = Product::query()
            ->with('fitments')
            ->where(function (Builder $query) use ($collection): void {
                $query->whereHas('fitments', function (Builder $fitments) use ($collection): void {
                    match (true) {
                        $collection->generation_id !== null => $fitments->where('generation_id', $collection->generation_id),
                        $collection->model_id !== null => $fitments->where('model_id', $collection->model_id),
                        $collection->make_id !== null => $fitments->where('make_id', $collection->make_id),
                        // A collection pointing at no vehicle can only ever hold manual links,
                        // so nothing automatic should match it.
                        default => $fitments->whereRaw('1 = 0'),
                    };
                })->orWhereHas('collections', fn (Builder $q) => $q->whereKey($collection->id));
            })
            ->get();

        foreach ($candidates as $product) {
            $matcher->syncForProduct($product);
        }

        $this->saved = 'Legăturile automate au fost recalculate ('.$candidates->count().' produse verificate).';
    }

    public function attachProduct(int $productId): void
    {
        if ($this->collectionId === null) {
            return;
        }

        // is_automatic = false marks it as an operator's decision, which the importer will not
        // undo on its next run.
        DB::table('product_vehicle_collection')->updateOrInsert(
            ['product_id' => $productId, 'vehicle_collection_id' => $this->collectionId],
            ['is_automatic' => false, 'updated_at' => now(), 'created_at' => now()],
        );

        $this->productSearch = '';
        $this->saved = 'Produsul a fost adăugat manual în colecție.';
    }

    public function detachProduct(int $productId): void
    {
        if ($this->collectionId === null) {
            return;
        }

        DB::table('product_vehicle_collection')
            ->where('vehicle_collection_id', $this->collectionId)
            ->where('product_id', $productId)
            ->delete();

        $this->saved = 'Produsul a fost scos din colecție.';
    }

    public function render()
    {
        return view('livewire.admin.catalog.collection-editor', [
            'tabs' => self::TABS,
            'makes' => VehicleMake::query()->withConfigurations()->orderBy('name')->get(['id', 'name']),
            'models' => $this->makeId === ''
                ? new EloquentCollection
                : VehicleModel::query()->where('make_id', $this->makeId)->withConfigurations()->orderBy('name')->get(['id', 'name']),
            'generations' => $this->modelId === ''
                ? new EloquentCollection
                : VehicleGeneration::query()->where('model_id', $this->modelId)->orderBy('year_from')->get(['id', 'name', 'year_from', 'year_to']),
            'products' => $this->attachedProducts(),
            'manualIds' => $this->manualProductIds(),
            'searchResults' => $this->searchResults(),
        ]);
    }

    /**
     * Files are stored only after validation has passed, so a rejected form never leaves an
     * orphan on disk.
     *
     * @return array<string, string>
     */
    private function storeUploads(): array
    {
        $stored = [];

        foreach (self::UPLOADS as $property => $column) {
            if ($this->{$property} !== null) {
                $stored[$column] = $this->{$property}->store('collections', 'public');
            } elseif ($this->images[$column] === null) {
                // Explicitly cleared through removeImage() on a record still being edited.
                $stored[$column] = null;
            }
        }

        return $stored;
    }

    /** @return EloquentCollection<int, Product> */
    private function attachedProducts(): EloquentCollection
    {
        if ($this->collectionId === null) {
            return new EloquentCollection;
        }

        return Product::query()
            ->with('brand:id,name')
            ->whereHas('collections', fn (Builder $q) => $q->whereKey($this->collectionId))
            ->orderBy('name')
            ->limit(200)
            ->get();
    }

    /**
     * The ids an operator attached by hand, so the list can mark them and the importer's own
     * rows can be told apart at a glance.
     *
     * @return list<int>
     */
    private function manualProductIds(): array
    {
        if ($this->collectionId === null) {
            return [];
        }

        return DB::table('product_vehicle_collection')
            ->where('vehicle_collection_id', $this->collectionId)
            ->where('is_automatic', false)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return EloquentCollection<int, Product> */
    private function searchResults(): EloquentCollection
    {
        if ($this->collectionId === null || mb_strlen($this->productSearch) < 3) {
            return new EloquentCollection;
        }

        return Product::query()
            ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($this->productSearch).'%'])
            ->whereDoesntHave('collections', fn (Builder $q) => $q->whereKey($this->collectionId))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'sku']);
    }
}
