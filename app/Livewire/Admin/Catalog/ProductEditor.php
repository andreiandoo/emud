<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\VehicleGeneration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin')]
class ProductEditor extends Component
{
    use WithFileUploads;

    public ?Product $product = null;

    public string $name = '';

    public string $slug = '';

    public string $sku = '';

    public string $manufacturerPartNumber = '';

    public ?int $brandId = null;

    public string $status = 'draft';

    public string $shortDescription = '';

    public string $description = '';

    public bool $isUniversal = false;

    public bool $isFeatured = false;

    public ?int $warrantyMonths = null;

    public $weightKg = null;

    public array $categoryIds = [];

    /** Nothing is offered in the category picker until this, or the browse-all toggle below. */
    public string $categorySearch = '';

    public bool $browseAllCategories = false;

    /** Nothing is offered in the fitment picker until this narrows the vehicle catalogue. */
    public string $fitmentSearch = '';

    public array $variants = [];

    public array $attributeValues = [];

    public array $fitments = [];

    public array $images = [];

    public string $seoTitle = '';

    public string $seoDescription = '';

    public string $canonicalUrl = '';

    public bool $robotsIndex = true;

    public bool $robotsFollow = true;

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->product = $product;
            $product->load(['categories', 'variants', 'attributeValues', 'fitments']);
            $this->name = $product->name;
            $this->slug = $product->slug;
            $this->sku = $product->sku ?? '';
            $this->manufacturerPartNumber = $product->manufacturer_part_number ?? '';
            $this->brandId = $product->brand_id;
            $this->status = $product->status->value;
            $this->shortDescription = $product->short_description ?? '';
            $this->description = $product->description ?? '';
            $this->isUniversal = $product->is_universal;
            $this->isFeatured = $product->is_featured;
            $this->warrantyMonths = $product->warranty_months;
            $this->weightKg = $product->weight_kg;
            $this->categoryIds = $product->categories->modelKeys();
            $this->variants = $product->variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'mpn' => $variant->manufacturer_part_number,
                'price' => $variant->retail_price,
                'compare_at_price' => $variant->compare_at_price,
                'currency' => $variant->currency,
                'weight_kg' => $variant->weight_kg,
                'is_active' => $variant->is_active,
            ])->all();
            $this->attributeValues = $product->attributeValues->mapWithKeys(fn ($value): array => [
                $value->attribute_id => $value->option_id ?: ($value->value_boolean ?? $value->value_number ?? $value->value_text),
            ])->all();
            $this->fitments = $product->fitments->map(fn ($fitment): array => [
                'id' => $fitment->id,
                'generation_id' => $fitment->generation_id,
                'year_from' => $fitment->year_from,
                'year_to' => $fitment->year_to,
                'position' => $fitment->position,
                'requires_modification' => $fitment->requires_modification,
                'notes' => $fitment->notes,
            ])->all();
            $this->seoTitle = $product->seo_title ?? '';
            $this->seoDescription = $product->seo_description ?? '';
            $this->canonicalUrl = $product->canonical_url ?? '';
            $this->robotsIndex = $product->robots_index;
            $this->robotsFollow = $product->robots_follow;
        }

        if ($this->variants === []) {
            $this->variants = [$this->emptyVariant()];
        }
    }

    public function updatedName(): void
    {
        if (! $this->product || $this->slug === '') {
            $this->slug = Str::slug($this->name);
        }
    }

    public function addVariant(): void
    {
        $this->variants[] = $this->emptyVariant();
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function addFitment(?int $generationId = null): void
    {
        // Adding the same generation twice would save two identical compatibility rows, which
        // then read as a data problem to anyone looking at the product later.
        if ($generationId !== null && collect($this->fitments)->contains('generation_id', $generationId)) {
            return;
        }

        $this->fitments[] = ['id' => null, 'generation_id' => $generationId, 'year_from' => null, 'year_to' => null, 'position' => '', 'requires_modification' => false, 'notes' => ''];
    }

    public function addCategory(int $categoryId): void
    {
        if (! in_array($categoryId, $this->categoryIds, true)) {
            $this->categoryIds[] = $categoryId;
        }

        $this->categorySearch = '';
        $this->browseAllCategories = false;
    }

    public function removeCategory(int $categoryId): void
    {
        $this->categoryIds = array_values(array_filter($this->categoryIds, fn (int $id): bool => $id !== $categoryId));
    }

    /**
     * The pills under the field, in the order they were picked — the first one is the primary
     * category on save, so the order is information, not decoration.
     *
     * @return \Illuminate\Support\Collection<int, Category>
     */
    public function getSelectedCategoriesProperty(): Collection
    {
        if ($this->categoryIds === []) {
            return collect();
        }

        $categories = Category::query()->whereIn('id', $this->categoryIds)->get(['id', 'name', 'full_path'])->keyBy('id');

        return collect($this->categoryIds)->map(fn (int $id) => $categories->get($id))->filter()->values();
    }

    public function removeFitment(int $index): void
    {
        unset($this->fitments[$index]);
        $this->fitments = array_values($this->fitments);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', 'unique:products,slug,'.($this->product?->id ?? 'NULL')],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku,'.($this->product?->id ?? 'NULL')],
            'manufacturerPartNumber' => ['nullable', 'string', 'max:255'],
            'brandId' => ['nullable', 'exists:brands,id'],
            'status' => ['required', 'in:draft,review,active,archived'],
            'categoryIds' => ['array', 'min:1'],
            'categoryIds.*' => ['exists:categories,id'],
            'variants' => ['array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'fitments.*.generation_id' => ['nullable', 'exists:vehicle_generations,id'],
            'fitments.*.year_from' => ['nullable', 'integer', 'between:1950,2100'],
            'fitments.*.year_to' => ['nullable', 'integer', 'between:1950,2100'],
            'images.*' => ['image', 'max:8192'],
            'seoTitle' => ['nullable', 'string', 'max:255'],
            'seoDescription' => ['nullable', 'string', 'max:320'],
            'canonicalUrl' => ['nullable', 'url', 'max:2048'],
        ]);

        DB::transaction(function () use ($validated): void {
            $payload = [
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'sku' => $validated['sku'] ?: null,
                'manufacturer_part_number' => $validated['manufacturerPartNumber'] ?: null,
                'brand_id' => $validated['brandId'],
                'status' => $validated['status'],
                'short_description' => $this->shortDescription ?: null,
                'description' => $this->description ?: null,
                'is_universal' => $this->isUniversal,
                'is_featured' => $this->isFeatured,
                'warranty_months' => $this->warrantyMonths,
                'weight_kg' => $this->weightKg ?: null,
                'seo_title' => $validated['seoTitle'] ?: null,
                'seo_description' => $validated['seoDescription'] ?: null,
                'canonical_url' => $validated['canonicalUrl'] ?: null,
                'robots_index' => $this->robotsIndex,
                'robots_follow' => $this->robotsFollow,
                'published_at' => $validated['status'] === 'active' ? ($this->product?->published_at ?? now()) : null,
            ];

            if ($this->product?->exists) {
                $product = $this->product;
                $product->update($payload);
            } else {
                $product = Product::query()->create($payload);
            }

            $product->categories()->sync(collect($this->categoryIds)->mapWithKeys(fn ($id, $index): array => [$id => ['is_primary' => $index === 0]])->all());

            $keptVariantIds = [];
            foreach ($this->variants as $position => $row) {
                $variantPayload = [
                    'name' => $row['name'] ?: null,
                    'sku' => $row['sku'],
                    'barcode' => $row['barcode'] ?: null,
                    'manufacturer_part_number' => $row['mpn'] ?: null,
                    'retail_price' => $row['price'] ?: null,
                    'compare_at_price' => $row['compare_at_price'] ?: null,
                    'currency' => $row['currency'] ?: 'RON',
                    'weight_kg' => $row['weight_kg'] ?: null,
                    'is_active' => (bool) $row['is_active'],
                    'position' => $position,
                ];

                if ($row['id'] ?? null) {
                    $variant = $product->variants()->whereKey($row['id'])->firstOrFail();
                    $variant->update($variantPayload);
                } else {
                    $variant = $product->variants()->create($variantPayload);
                }
                $keptVariantIds[] = $variant->id;
            }
            $product->variants()->whereNotIn('id', $keptVariantIds)->delete();

            $product->attributeValues()->delete();
            foreach (Attribute::whereIn('id', array_keys($this->attributeValues))->get() as $attribute) {
                $value = $this->attributeValues[$attribute->id] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $data = ['attribute_id' => $attribute->id];
                if (in_array($attribute->type, ['select', 'color'], true)) {
                    $data['option_id'] = $value;
                } elseif ($attribute->type === 'number') {
                    $data['value_number'] = $value;
                } elseif ($attribute->type === 'boolean') {
                    $data['value_boolean'] = (bool) $value;
                } elseif ($attribute->type === 'multiselect') {
                    $data['value_json'] = (array) $value;
                } else {
                    $data['value_text'] = $value;
                }
                $product->attributeValues()->create($data);
            }

            $keptFitmentIds = [];
            foreach ($this->fitments as $row) {
                if (! ($row['generation_id'] ?? null)) {
                    continue;
                }
                $generation = VehicleGeneration::with('model')->findOrFail($row['generation_id']);
                $fitmentPayload = [
                    'make_id' => $generation->model->make_id,
                    'model_id' => $generation->model_id,
                    'generation_id' => $generation->id,
                    'year_from' => $row['year_from'] ?: $generation->year_from,
                    'year_to' => $row['year_to'] ?: $generation->year_to,
                    'position' => $row['position'] ?: null,
                    'requires_modification' => (bool) $row['requires_modification'],
                    'notes' => $row['notes'] ?: null,
                    'source' => 'admin',
                ];

                if ($row['id'] ?? null) {
                    $fitment = $product->fitments()->whereKey($row['id'])->firstOrFail();
                    $fitment->update($fitmentPayload);
                } else {
                    $fitment = $product->fitments()->create($fitmentPayload);
                }
                $keptFitmentIds[] = $fitment->id;
            }
            $product->fitments()->whereNotIn('id', $keptFitmentIds)->delete();

            foreach ($this->images as $index => $image) {
                $product->media()->create([
                    'type' => 'image',
                    'disk' => 'public',
                    'path' => $image->store('products', 'public'),
                    'alt_text' => $product->name,
                    'position' => $product->media()->count() + $index,
                ]);
            }
            $this->product = $product;
        });

        session()->flash('success', 'Produsul a fost salvat complet.');
        $this->redirectRoute('admin.products.edit', $this->product, navigate: true);
    }

    public function removeMedia(int $mediaId): void
    {
        $this->product?->media()->whereKey($mediaId)->delete();
    }

    public function deleteProduct(): void
    {
        abort_unless($this->product?->exists, 404);
        $this->product->delete();
        session()->flash('success', 'Produsul a fost mutat în arhivă.');
        $this->redirectRoute('admin.products.index', navigate: true);
    }

    private function emptyVariant(): array
    {
        return ['id' => null, 'name' => 'Standard', 'sku' => '', 'barcode' => '', 'mpn' => '', 'price' => null, 'compare_at_price' => null, 'currency' => 'RON', 'weight_kg' => null, 'is_active' => true];
    }

    public function render()
    {
        $categoryIds = $this->categoryIds;
        $attributes = Attribute::with('options')->where('is_active', true)->where(function ($query) use ($categoryIds): void {
            $query->where('is_global', true)->orWhereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));
        })->orderBy('name')->get();

        return view('livewire.admin.catalog.product-editor', [
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => $this->categoryOptions(),
            'attributes' => $attributes,
            'generations' => $this->generationOptions(),
            'existingMedia' => $this->product?->media()->get() ?? collect(),
        ]);
    }

    /**
     * Candidates to add, never the whole tree by default. What is already on the product lives
     * in the pills underneath, so it is deliberately excluded here rather than shown twice.
     *
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function categoryOptions(): Collection
    {
        $search = trim($this->categorySearch);

        if ($search === '' && ! $this->browseAllCategories) {
            return collect();
        }

        return Category::query()
            ->where('is_active', true)
            ->whereNotIn('id', $this->categoryIds ?: [0])
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('name', 'ilike', '%'.$search.'%')
                ->orWhere('full_path', 'ilike', '%'.$search.'%')))
            ->orderBy('full_path')
            ->limit($search === '' ? 400 : 40)
            ->get(['id', 'name', 'full_path', 'depth']);
    }

    /**
     * The vehicle catalogue holds tens of thousands of generations, and this select is rendered
     * once per fitment row, so listing them all produced hundreds of thousands of options and
     * froze the page for twenty seconds before a single click landed. Nothing is listed until
     * there is something to list it for: a search, or a generation already on the product.
     *
     * Built as a plain query rather than Eloquent — these rows are option labels, and hydrating
     * three models each to print one line of text is the expensive part.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function generationOptions(): Collection
    {
        $search = trim($this->fitmentSearch);

        if ($search === '') {
            return collect();
        }

        return $this->generationQuery()
            ->whereNotIn('vg.id', $this->selectedGenerationIds() ?: [0])
            ->where(fn ($match) => $match
                ->where('mk.name', 'ilike', '%'.$search.'%')
                ->orWhere('vm.name', 'ilike', '%'.$search.'%')
                ->orWhere('vg.name', 'ilike', '%'.$search.'%'))
            ->limit(40)
            ->get();
    }

    /**
     * Labels for the generations already on the product, so a compatibility row can print what
     * it refers to without a select holding every generation in the catalogue.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getFitmentLabelsProperty(): Collection
    {
        $selected = $this->selectedGenerationIds();

        if ($selected === []) {
            return collect();
        }

        return $this->generationQuery()
            ->whereIn('vg.id', $selected)
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->id => $row->label.' ('.$row->year_from.'–'.($row->year_to ?: 'prezent').')',
            ]);
    }

    /** @return list<int> */
    private function selectedGenerationIds(): array
    {
        return collect($this->fitments)->pluck('generation_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    /**
     * Built as a plain query rather than Eloquent: these rows are option labels, and hydrating
     * three models each to print one line of text was most of what made this page unusable.
     */
    private function generationQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('vehicle_generations as vg')
            ->join('vehicle_models as vm', 'vm.id', '=', 'vg.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'vm.make_id')
            ->orderBy('mk.name')
            ->orderBy('vm.name')
            ->orderBy('vg.year_from')
            ->select([
                'vg.id',
                'vg.year_from',
                'vg.year_to',
                DB::raw("mk.name || ' ' || vm.name || ' · ' || coalesce(vg.name, '') as label"),
            ]);
    }
}
