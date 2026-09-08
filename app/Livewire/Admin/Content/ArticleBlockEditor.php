<?php

namespace App\Livewire\Admin\Content;

use App\Content\VideoEmbed;
use App\Enums\ArticleBlockType;
use App\Models\Article;
use App\Models\ArticleBlock;
use App\Models\ArticleVehicle;
use App\Models\Category;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class ArticleBlockEditor extends Component
{
    public Article $article;

    /** @var array<int, array<string, mixed>> */
    public array $blocks = [];

    public string $newBlockType = ArticleBlockType::RichText->value;

    public ?int $vehicleMakeId = null;

    public ?int $vehicleModelId = null;

    public ?int $vehicleGenerationId = null;

    public string $status = '';

    public function mount(Article $article): void
    {
        $this->article = $article;
        $this->loadBlocks();
    }

    public function addBlock(): void
    {
        $this->validate(['newBlockType' => ['required', Rule::enum(ArticleBlockType::class)]]);

        ArticleBlock::create([
            'article_id' => $this->article->id,
            'type' => $this->newBlockType,
            'position' => ((int) $this->article->blocks()->max('position')) + 1,
            'data' => $this->defaultDataFor(ArticleBlockType::from($this->newBlockType)),
        ]);

        $this->loadBlocks();
        $this->status = 'Blocul a fost adăugat.';
    }

    public function saveBlocks(): void
    {
        foreach ($this->blocks as $index => $block) {
            $model = $this->ownedBlock((int) $block['id']);
            $type = $model->type;

            $model->update([
                // Position comes from the array order, so dragging or moving rows is enough to
                // reorder; the stored number is never edited by hand.
                'position' => $index,
                'data' => $this->cleanData($type, (array) ($block['data'] ?? [])),
            ]);
        }

        $this->loadBlocks();
        $this->status = 'Blocurile au fost salvate.';
    }

    public function moveBlock(int $blockId, int $direction): void
    {
        $ordered = $this->article->blocks()->get();
        $index = $ordered->search(fn (ArticleBlock $block) => $block->id === $blockId);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;

        if ($target < 0 || $target >= $ordered->count()) {
            return;
        }

        // Swap the two positions rather than renumbering the whole list, so a long article does
        // not rewrite every row to move one block.
        $moving = $ordered[$index];
        $displaced = $ordered[$target];

        [$moving->position, $displaced->position] = [$displaced->position, $moving->position];
        $moving->save();
        $displaced->save();

        $this->loadBlocks();
    }

    public function removeBlock(int $blockId): void
    {
        $this->ownedBlock($blockId)->delete();
        $this->loadBlocks();
        $this->status = 'Blocul a fost șters.';
    }

    public function addVehicle(): void
    {
        $this->validate([
            'vehicleMakeId' => ['required', 'integer', 'exists:vehicle_makes,id'],
            'vehicleModelId' => ['nullable', 'integer', Rule::exists('vehicle_models', 'id')->where('make_id', $this->vehicleMakeId)],
            'vehicleGenerationId' => ['nullable', 'integer', Rule::exists('vehicle_generations', 'id')->where('model_id', $this->vehicleModelId)],
        ], [
            'vehicleModelId.exists' => 'Modelul ales nu aparține acestei mărci.',
            'vehicleGenerationId.exists' => 'Generația aleasă nu aparține acestui model.',
        ]);

        // firstOrCreate: linking the same vehicle twice adds nothing, and the unique index would
        // reject it anyway.
        ArticleVehicle::query()->firstOrCreate([
            'article_id' => $this->article->id,
            'make_id' => $this->vehicleMakeId,
            'model_id' => $this->vehicleModelId,
            'generation_id' => $this->vehicleGenerationId,
        ]);

        $this->reset(['vehicleMakeId', 'vehicleModelId', 'vehicleGenerationId']);
        $this->status = 'Mașina a fost legată de articol.';
    }

    public function removeVehicle(int $linkId): void
    {
        $this->article->vehicles()->where('id', $linkId)->delete();
        $this->status = 'Legătura a fost ștearsă.';
    }

    public function updatedVehicleMakeId(): void
    {
        $this->vehicleModelId = null;
        $this->vehicleGenerationId = null;
    }

    public function updatedVehicleModelId(): void
    {
        $this->vehicleGenerationId = null;
    }

    public function render()
    {
        return view('livewire.admin.content.article-block-editor', [
            'types' => ArticleBlockType::cases(),
            'vehicles' => $this->article->vehicles()->with(['make', 'model', 'generation'])->get(),
            'makes' => VehicleMake::query()->where('is_active', true)->orderBy('name')->get(),
            'models' => $this->models(),
            'generations' => $this->generations(),
            'categories' => Category::query()->where('is_active', true)->orderBy('full_path')->get(['id', 'name', 'full_path']),
        ]);
    }

    /** @return Collection<int, VehicleModel> */
    private function models(): Collection
    {
        return $this->vehicleMakeId === null
            ? collect()
            : VehicleModel::query()->where('make_id', $this->vehicleMakeId)->orderBy('name')->get();
    }

    /** @return Collection<int, VehicleGeneration> */
    private function generations(): Collection
    {
        return $this->vehicleModelId === null
            ? collect()
            : VehicleGeneration::query()->where('model_id', $this->vehicleModelId)->orderByDesc('year_from')->get();
    }

    private function loadBlocks(): void
    {
        $this->article->refresh();

        $this->blocks = $this->article->blocks()->get()->map(fn (ArticleBlock $block): array => [
            'id' => $block->id,
            'type' => $block->type->value,
            'label' => $block->type->label(),
            'data' => $block->data ?? [],
        ])->all();
    }

    /**
     * Each block kind keeps only the keys it understands. Without this the payload would accept
     * whatever the form posted, and a renamed field would leave dead keys behind forever.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cleanData(ArticleBlockType $type, array $data): array
    {
        return match ($type) {
            ArticleBlockType::RichText => ['html' => (string) ($data['html'] ?? '')],
            ArticleBlockType::Image => [
                'url' => (string) ($data['url'] ?? ''),
                'alt' => (string) ($data['alt'] ?? ''),
                'caption' => (string) ($data['caption'] ?? ''),
            ],
            ArticleBlockType::Gallery => ['images' => $this->cleanList($data['images'] ?? [], ['url', 'alt'])],
            ArticleBlockType::Video => [
                // Stored as given but validated here so the author is told immediately, rather
                // than the article rendering an empty frame to readers.
                'url' => (string) ($data['url'] ?? ''),
                'caption' => (string) ($data['caption'] ?? ''),
                'recognised' => VideoEmbed::fromUrl($data['url'] ?? null) !== null,
            ],
            ArticleBlockType::PartsCarousel => [
                'title' => (string) ($data['title'] ?? 'Piese recomandate'),
                'source' => in_array($data['source'] ?? '', ['products', 'category', 'vehicle'], true) ? $data['source'] : 'products',
                'category_id' => ($data['category_id'] ?? null) ? (int) $data['category_id'] : null,
                'product_ids' => array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) ($data['product_ids'] ?? ''))))),
                'limit' => max(1, min(12, (int) ($data['limit'] ?? 8))),
            ],
            ArticleBlockType::Callout => [
                'title' => (string) ($data['title'] ?? ''),
                'tone' => in_array($data['tone'] ?? '', ['info', 'warning', 'danger'], true) ? $data['tone'] : 'info',
                'html' => (string) ($data['html'] ?? ''),
            ],
            ArticleBlockType::Steps => ['steps' => $this->cleanList($data['steps'] ?? [], ['title', 'text'])],
        };
    }

    /**
     * @param  list<string>  $keys
     * @return list<array<string, string>>
     */
    private function cleanList(mixed $rows, array $keys): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->map(fn (mixed $row): array => collect($keys)
                ->mapWithKeys(fn (string $key): array => [$key => (string) data_get($row, $key, '')])
                ->all())
            ->filter(fn (array $row): bool => trim(implode('', $row)) !== '')
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function defaultDataFor(ArticleBlockType $type): array
    {
        return match ($type) {
            ArticleBlockType::PartsCarousel => ['title' => 'Piese recomandate', 'source' => 'vehicle', 'limit' => 8],
            ArticleBlockType::Callout => ['tone' => 'info'],
            ArticleBlockType::Gallery => ['images' => []],
            ArticleBlockType::Steps => ['steps' => []],
            default => [],
        };
    }

    /**
     * Block ids come from the rendered form, so each one is re-read through this article. A
     * block belonging to another article must not be editable by changing an id.
     */
    private function ownedBlock(int $blockId): ArticleBlock
    {
        return ArticleBlock::query()->where('article_id', $this->article->id)->findOrFail($blockId);
    }
}
