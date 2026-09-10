<?php

namespace Database\Seeders;

use App\Models\VehicleCollection;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds a collection for every car the vehicle graph actually knows a real vehicle for.
 *
 * Two levels: one per make ("Toyota") and one per model ("Toyota Land Cruiser"). Both are needed
 * because they answer different questions — the carousel on the home page asks "what do you
 * drive", the collection page asks "what fits it" — and because CollectionMatcher attaches a part
 * to every level its fitment touches.
 *
 * Scoped through withConfigurations(): vPIC contributes every registered US manufacturer as
 * reference data, trailer builders and welding shops included, and a collection page for a
 * company that has never made a 4x4 is a page with nothing on it.
 *
 * Re-running only adds what is missing. Rows that already exist are left completely alone — the
 * photos, the copy and the featured flag on them are an operator's work, and a seeder that
 * refreshed "just the name" would quietly undo a rename.
 */
class VehicleCollectionSeeder extends Seeder
{
    /**
     * Makes this shop actually sells for, put in front of the visitor by default.
     *
     * Featuring every make would fill the carousel with cars nobody comes here for; featuring
     * none would leave it empty on a fresh install. The order is the order they appear in.
     */
    public const FEATURED_MAKES = [
        'Suzuki', 'Toyota', 'Jeep', 'Land Rover', 'Nissan', 'Mitsubishi',
        'Ford', 'Dacia', 'Isuzu', 'Mercedes-Benz', 'Volkswagen', 'Lada',
    ];

    public function run(): void
    {
        // Makes first and committed before the models, because a model collection has to carry
        // the id of its parent and that id does not exist until the make row is written.
        $this->seedMakes();
        $this->seedModels();
    }

    private function seedMakes(): void
    {
        $existing = VehicleCollection::query()
            ->whereNotNull('make_id')
            ->whereNull('model_id')
            ->pluck('make_id')
            ->flip();

        $takenSlugs = VehicleCollection::query()->pluck('slug')->flip();
        $featured = array_flip(self::FEATURED_MAKES);
        $rows = [];
        $now = now();

        foreach (VehicleMake::query()->withConfigurations()->orderBy('name')->get(['id', 'name']) as $make) {
            if ($existing->has($make->id)) {
                continue;
            }

            $rows[] = [
                'parent_id' => null,
                'make_id' => $make->id,
                'model_id' => null,
                'generation_id' => null,
                'name' => $make->name,
                'slug' => $this->uniqueSlug($make->name, $takenSlugs),
                'subtitle' => 'Piese și accesorii pentru '.$make->name,
                // Present and null rather than absent: a bulk insert needs every row to carry
                // the same columns, and a make-level collection spans no years.
                'year_from' => null,
                'year_to' => null,
                'is_featured' => isset($featured[$make->name]),
                'position' => $featured[$make->name] ?? 100,
                'is_active' => true,
                'robots_index' => true,
                'robots_follow' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insert($rows);
    }

    private function seedModels(): void
    {
        // Read back after the make pass so newly created parents are in hand, keyed by the make
        // they describe.
        $parents = VehicleCollection::query()
            ->whereNotNull('make_id')
            ->whereNull('model_id')
            ->whereNull('generation_id')
            ->pluck('id', 'make_id');

        $existing = VehicleCollection::query()
            ->whereNotNull('model_id')
            ->whereNull('generation_id')
            ->pluck('model_id')
            ->flip();

        $takenSlugs = VehicleCollection::query()->pluck('slug')->flip();
        $rows = [];
        $now = now();

        $makeNames = VehicleMake::query()->withConfigurations()->pluck('name', 'id');

        VehicleModel::query()
            ->whereIn('make_id', $makeNames->keys())
            ->withConfigurations()
            ->with('generations:id,model_id,year_from,year_to')
            // No orderBy: chunkById walks the table by id, and a name ordering left in place
            // makes the last row of a chunk not the highest id, which silently skips models.
            ->chunkById(500, function ($models) use (&$rows, $existing, $parents, $makeNames, $takenSlugs, $now): void {
                foreach ($models as $model) {
                    if ($existing->has($model->id)) {
                        continue;
                    }

                    $name = trim($makeNames->get($model->make_id).' '.$model->name);
                    $years = $model->generations;

                    $rows[] = [
                        // Null when the make somehow has no collection of its own; the row is
                        // then a root in its own right rather than an orphan pointing nowhere.
                        'parent_id' => $parents->get($model->make_id),
                        'make_id' => $model->make_id,
                        'model_id' => $model->id,
                        'generation_id' => null,
                        'name' => $name,
                        'slug' => $this->uniqueSlug($name, $takenSlugs),
                        'subtitle' => 'Piese și accesorii pentru '.$name,
                        // Nulls stay null: a model whose generations carry no years should say
                        // nothing rather than claim a range it does not know.
                        'year_from' => $years->pluck('year_from')->filter()->min(),
                        'year_to' => $years->pluck('year_to')->filter()->max(),
                        'is_featured' => false,
                        'position' => 100,
                        'is_active' => true,
                        'robots_index' => true,
                        'robots_follow' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            });

        $this->insert($rows);
    }

    /**
     * Chunked because a full vehicle graph produces thousands of rows and a single insert with
     * that many bindings exceeds what the driver will accept. Every row carries an identical set
     * of columns, which insert() requires of a multi-row batch.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('vehicle_collections')->insert($chunk);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $taken
     */
    private function uniqueSlug(string $name, $taken): string
    {
        $base = Str::slug($name) ?: 'colectie';
        $slug = $base;
        $suffix = 2;

        while ($taken->has($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        $taken->put($slug, true);

        return $slug;
    }
}
