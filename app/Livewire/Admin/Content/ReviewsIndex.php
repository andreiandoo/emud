<?php

namespace App\Livewire\Admin\Content;

use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class ReviewsIndex extends Component
{
    use WithPagination;

    /** @var array<string, string> */
    public const TABS = [
        '' => 'Toate',
        'published' => 'Publicate',
        'draft' => 'Ciorne',
        'featured' => 'Evidențiate',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $tab = '';

    public string $flash = '';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    /**
     * Publishing fills published_at when it is still empty, because the storefront filters on the
     * date as well as the flag — a review marked published with no date would never appear, and
     * the operator would have no way of telling why.
     */
    public function togglePublished(int $reviewId): void
    {
        $review = Review::query()->findOrFail($reviewId);

        if ($review->status === 'published') {
            $review->update(['status' => 'draft']);
            $this->flash = 'Recenzia a fost retrasă.';

            return;
        }

        $review->update([
            'status' => 'published',
            'published_at' => $review->published_at ?? now(),
        ]);

        $this->flash = 'Recenzia este publicată.';
    }

    public function toggleFeatured(int $reviewId): void
    {
        $review = Review::query()->findOrFail($reviewId);
        $review->update(['is_featured' => ! $review->is_featured]);

        $this->flash = $review->is_featured ? 'Recenzia este evidențiată.' : 'Recenzia nu mai este evidențiată.';
    }

    public function delete(int $reviewId): void
    {
        Review::query()->whereKey($reviewId)->delete();

        $this->flash = 'Recenzia a fost ștearsă.';
    }

    public function render()
    {
        return view('livewire.admin.content.reviews-index', [
            'reviews' => $this->query()
                ->with(['product:id,name', 'collection:id,name', 'make:id,name', 'model:id,name'])
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(25),
            'tabs' => self::TABS,
            'counts' => [
                '' => Review::query()->count(),
                'published' => Review::query()->where('status', 'published')->count(),
                'draft' => Review::query()->where('status', 'draft')->count(),
                'featured' => Review::query()->where('is_featured', true)->count(),
            ],
        ]);
    }

    private function query(): Builder
    {
        return Review::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('lower(reviewer_name) like ?', [$term])
                    ->orWhereRaw('lower(body) like ?', [$term]));
            })
            ->when($this->tab === 'published', fn (Builder $q) => $q->where('status', 'published'))
            ->when($this->tab === 'draft', fn (Builder $q) => $q->where('status', 'draft'))
            ->when($this->tab === 'featured', fn (Builder $q) => $q->where('is_featured', true));
    }
}
