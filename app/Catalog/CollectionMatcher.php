<?php

namespace App\Catalog;

use App\Models\Product;
use App\Models\VehicleCollection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Puts an imported product into the right vehicle collections.
 *
 * A supplier feed never mentions collections — it names vehicles, and the importer turns those
 * into fitment rows. This walks those rows back up the graph: a part that fits Suzuki Jimny III
 * belongs in the Jimny collection *and* in the Suzuki one, because a collection is a slice of the
 * vehicle graph and slices nest. It is deliberately not "most specific match wins".
 *
 * Only links it created are its to remove. An operator who adds a product to a collection by hand
 * is making an editorial decision the next import has no business overruling, so those rows carry
 * is_automatic = false and are left exactly where they are.
 */
class CollectionMatcher
{
    /** @var array{generation: array<int, list<object>>, model: array<int, list<object>>, make: array<int, list<object>>}|null */
    private ?array $index = null;

    /**
     * Re-derives one product's automatic collection links.
     *
     * @return int the number of collections the product now sits in automatically
     */
    public function syncForProduct(Product $product): int
    {
        $wanted = $this->collectionIdsFor($product);

        $existing = DB::table('product_vehicle_collection')
            ->where('product_id', $product->id)
            ->get(['vehicle_collection_id', 'is_automatic']);

        $manual = $existing->where('is_automatic', false)->pluck('vehicle_collection_id')->all();
        $automatic = $existing->where('is_automatic', true)->pluck('vehicle_collection_id')->all();

        // A collection an operator pinned by hand is already there; adding an automatic row for
        // it would fail the primary key, and overwriting it would erase their decision.
        $toAdd = array_diff($wanted, $automatic, $manual);
        $toRemove = array_diff($automatic, $wanted);

        DB::transaction(function () use ($product, $toAdd, $toRemove): void {
            if ($toRemove !== []) {
                DB::table('product_vehicle_collection')
                    ->where('product_id', $product->id)
                    ->where('is_automatic', true)
                    ->whereIn('vehicle_collection_id', $toRemove)
                    ->delete();
            }

            if ($toAdd !== []) {
                $now = now();
                DB::table('product_vehicle_collection')->insert(array_map(
                    fn (int $id): array => [
                        'product_id' => $product->id,
                        'vehicle_collection_id' => $id,
                        'is_automatic' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    array_values($toAdd),
                ));
            }
        });

        return count($wanted);
    }

    /**
     * The whole catalogue, in chunks.
     *
     * @param  callable(int):void|null  $progress  called with the number of products done so far
     * @return array{products: int, links: int}
     */
    public function syncAll(?callable $progress = null): array
    {
        $this->index = null;
        $products = 0;
        $links = 0;

        Product::query()
            ->with('fitments')
            ->orderBy('id')
            ->chunkById(200, function (EloquentCollection $chunk) use (&$products, &$links, $progress): void {
                foreach ($chunk as $product) {
                    $links += $this->syncForProduct($product);
                    $products++;
                }

                if ($progress !== null) {
                    $progress($products);
                }
            });

        return ['products' => $products, 'links' => $links];
    }

    /**
     * @return list<int>
     */
    public function collectionIdsFor(Product $product): array
    {
        $fitments = $product->relationLoaded('fitments') ? $product->fitments : $product->fitments()->get();

        if ($fitments->isEmpty()) {
            return [];
        }

        $index = $this->index();
        $matched = [];

        foreach ($fitments as $fitment) {
            $candidates = [
                ...($fitment->generation_id ? ($index['generation'][$fitment->generation_id] ?? []) : []),
                ...($fitment->model_id ? ($index['model'][$fitment->model_id] ?? []) : []),
                ...($fitment->make_id ? ($index['make'][$fitment->make_id] ?? []) : []),
            ];

            foreach ($candidates as $collection) {
                if ($this->yearsOverlap($collection, $fitment->year_from, $fitment->year_to)) {
                    $matched[$collection->id] = true;
                }
            }
        }

        return array_map('intval', array_keys($matched));
    }

    /** Drops the cached collection index; call after collections change mid-process. */
    public function forget(): void
    {
        $this->index = null;
    }

    /**
     * Collections bucketed by the level they describe.
     *
     * A make-level bucket holds only collections that name a make and nothing narrower — a
     * fitment for "any Suzuki" must not drag in every Suzuki model collection, because a part
     * that fits the whole range is not evidence it belongs on the Jimny page.
     *
     * @return array{generation: array<int, list<object>>, model: array<int, list<object>>, make: array<int, list<object>>}
     */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $rows = VehicleCollection::query()
            ->where('is_active', true)
            ->get(['id', 'make_id', 'model_id', 'generation_id', 'year_from', 'year_to']);

        $index = ['generation' => [], 'model' => [], 'make' => []];

        foreach ($rows as $row) {
            $bucket = match (true) {
                $row->generation_id !== null => ['generation', $row->generation_id],
                $row->model_id !== null => ['model', $row->model_id],
                $row->make_id !== null => ['make', $row->make_id],
                default => null,
            };

            if ($bucket === null) {
                continue;
            }

            [$level, $key] = $bucket;
            $index[$level][$key][] = (object) [
                'id' => (int) $row->id,
                'year_from' => $row->year_from,
                'year_to' => $row->year_to,
            ];
        }

        return $this->index = $index;
    }

    /**
     * Two half-open ranges intersect unless one ends before the other starts. A missing bound
     * means "unbounded", which is why every comparison is guarded rather than defaulted: a
     * collection with no years declared matches any fitment, and that is the common case.
     */
    private function yearsOverlap(object $collection, ?int $fitmentFrom, ?int $fitmentTo): bool
    {
        if ($collection->year_to !== null && $fitmentFrom !== null && $fitmentFrom > $collection->year_to) {
            return false;
        }

        return ! ($collection->year_from !== null && $fitmentTo !== null && $fitmentTo < $collection->year_from);
    }
}
