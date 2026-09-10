<?php

namespace App\Livewire\Admin\Content;

use App\Models\Product;
use App\Models\Review;
use App\Models\VehicleCollection;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * One review, written up by an operator from what a customer actually sent.
 *
 * Held by id rather than as a typed Review property, for the same reason as every other editor
 * here: a route parameter sharing a name with a typed model property is resolved before mount().
 */
#[Layout('layouts::admin')]
class ReviewEditor extends Component
{
    use WithFileUploads;

    public ?int $reviewId = null;

    public string $reviewerName = '';

    public string $reviewerLocation = '';

    public string $reviewerInstagram = '';

    public string $reviewerFacebook = '';

    public string $title = '';

    public string $body = '';

    public string $rating = '';

    public string $videoUrl = '';

    public string $productId = '';

    public string $productSearch = '';

    public string $collectionId = '';

    public string $collectionSearch = '';

    public string $makeId = '';

    public string $modelId = '';

    public string $vehicleLabel = '';

    public string $reviewStatus = 'draft';

    public bool $isFeatured = false;

    public string $publishedAt = '';

    public string $position = '0';

    public mixed $image = null;

    public ?string $imagePath = null;

    public string $saved = '';

    public function mount(?Review $review = null): void
    {
        if (! $review?->exists) {
            return;
        }

        $this->fill([
            'reviewId' => $review->id,
            'reviewerName' => (string) $review->reviewer_name,
            'reviewerLocation' => (string) $review->reviewer_location,
            'reviewerInstagram' => (string) $review->reviewer_instagram,
            'reviewerFacebook' => (string) $review->reviewer_facebook,
            'title' => (string) $review->title,
            'body' => (string) $review->body,
            'rating' => (string) ($review->rating ?? ''),
            'videoUrl' => (string) $review->video_url,
            'productId' => (string) ($review->product_id ?? ''),
            'collectionId' => (string) ($review->vehicle_collection_id ?? ''),
            'makeId' => (string) ($review->make_id ?? ''),
            'modelId' => (string) ($review->model_id ?? ''),
            'vehicleLabel' => (string) $review->vehicle_label,
            'reviewStatus' => (string) $review->status,
            'isFeatured' => (bool) $review->is_featured,
            'publishedAt' => $review->published_at?->format('Y-m-d\TH:i') ?? '',
            'position' => (string) $review->position,
            'imagePath' => $review->image_path,
        ]);
    }

    public function updatedMakeId(): void
    {
        $this->modelId = '';
    }

    public function selectProduct(int $productId): void
    {
        $this->productId = (string) $productId;
        $this->productSearch = '';
    }

    public function clearProduct(): void
    {
        $this->productId = '';
    }

    public function selectCollection(int $collectionId): void
    {
        $this->collectionId = (string) $collectionId;
        $this->collectionSearch = '';
    }

    public function clearCollection(): void
    {
        $this->collectionId = '';
    }

    public function removeImage(): void
    {
        $this->imagePath = null;

        if ($this->reviewId !== null) {
            Review::query()->whereKey($this->reviewId)->update(['image_path' => null]);
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'reviewerName' => ['required', 'string', 'max:120'],
            'reviewerLocation' => ['nullable', 'string', 'max:120'],
            'reviewerInstagram' => ['nullable', 'string', 'max:255'],
            'reviewerFacebook' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'videoUrl' => ['nullable', 'url', 'max:2048'],
            'productId' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'collectionId' => ['nullable', 'integer', Rule::exists('vehicle_collections', 'id')],
            'makeId' => ['nullable', 'integer', Rule::exists('vehicle_makes', 'id')],
            'modelId' => ['nullable', 'integer', Rule::exists('vehicle_models', 'id')],
            'vehicleLabel' => ['nullable', 'string', 'max:160'],
            'reviewStatus' => ['required', Rule::in(['draft', 'published'])],
            'publishedAt' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image' => ['nullable', 'image', 'max:6144'],
        ], [], [
            'reviewerName' => 'numele clientului',
            'body' => 'textul recenziei',
            'videoUrl' => 'linkul video',
        ]);

        $review = $this->reviewId === null
            ? new Review
            : Review::query()->findOrFail($this->reviewId);

        // A review published with no date would pass the flag check and fail the date check, so
        // it would sit in the back office looking live and never appear on the shop.
        $publishedAt = $data['publishedAt'] ?: null;
        if ($data['reviewStatus'] === 'published' && $publishedAt === null) {
            $publishedAt = now();
        }

        $review->fill([
            'reviewer_name' => $data['reviewerName'],
            'reviewer_location' => $data['reviewerLocation'] ?: null,
            'reviewer_instagram' => $data['reviewerInstagram'] ?: null,
            'reviewer_facebook' => $data['reviewerFacebook'] ?: null,
            'title' => $data['title'] ?: null,
            'body' => $data['body'],
            'rating' => $data['rating'] ?: null,
            'video_url' => $data['videoUrl'] ?: null,
            'product_id' => $data['productId'] ?: null,
            'vehicle_collection_id' => $data['collectionId'] ?: null,
            'make_id' => $data['makeId'] ?: null,
            'model_id' => $data['modelId'] ?: null,
            'vehicle_label' => $data['vehicleLabel'] ?: null,
            'status' => $data['reviewStatus'],
            'is_featured' => $this->isFeatured,
            'published_at' => $publishedAt,
            'position' => (int) ($data['position'] ?: 0),
            'image_disk' => 'public',
        ]);

        if ($this->image !== null) {
            $review->image_path = $this->image->store('reviews', 'public');
        } elseif ($this->imagePath === null) {
            $review->image_path = null;
        }

        $review->save();

        $this->reviewId = $review->id;
        $this->imagePath = $review->image_path;
        $this->publishedAt = $review->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->reset('image');

        $this->saved = 'Recenzia a fost salvată.';
    }

    public function render()
    {
        return view('livewire.admin.content.review-editor', [
            'makes' => VehicleMake::query()->withConfigurations()->orderBy('name')->get(['id', 'name']),
            'models' => $this->makeId === ''
                ? new EloquentCollection
                : VehicleModel::query()->where('make_id', $this->makeId)->withConfigurations()->orderBy('name')->get(['id', 'name']),
            'selectedProduct' => $this->productId === '' ? null : Product::query()->find((int) $this->productId),
            'selectedCollection' => $this->collectionId === '' ? null : VehicleCollection::query()->find((int) $this->collectionId),
            'productResults' => $this->search(Product::query(), $this->productSearch),
            'collectionResults' => $this->search(VehicleCollection::query(), $this->collectionSearch),
            'imageUrl' => $this->imagePath === null ? null : Storage::disk('public')->url($this->imagePath),
        ]);
    }

    /**
     * Both pickers search a `name` column and return at most eight rows, so one helper serves
     * products and collections alike.
     *
     * @return EloquentCollection<int, \Illuminate\Database\Eloquent\Model>
     */
    private function search(Builder $query, string $term): EloquentCollection
    {
        if (mb_strlen($term) < 3) {
            return new EloquentCollection;
        }

        return $query
            ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($term).'%'])
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name']);
    }
}
