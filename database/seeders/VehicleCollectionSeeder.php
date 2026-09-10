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
        $existing = VehicleCollection::query()
            ->whereNotNull('make_id')
            ->get(['id', 'make_id', 'model_id'])
            ->keyBy(fn (VehicleCollection $row): string => $row->make_id.':'.($row->model_id ?? ''));

        $takenSlugs = VehicleCollection::query()->pluck('slug')->flip();
        $featured = array_flip(self::FEATURED_MAKES);
        $rows = [];
        $now = now();

        $makes = VehicleMake::query()->withConfigurations()->orderBy('name')->get(['id', 'name']);

        foreach ($makes as $make) {
            if (! $existing->has($make->id.':')) {
                $rows[] = [
                    'make_id' => $make->id,
                    'model_id' => null,
                    'generation_id' => null,
                    'name' => $make->name,
                    'slug' => $this->uniqueSlug($make->name, $takenSlugs),
                    'subtitle' => 'Piese și accesorii pentru '.$make->name,
                    // Present and null rather than absent: a bulk insert needs every row to
                    // carry the same columns, and a make-level collection spans no years.
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

            $models = VehicleModel::query()
                ->where('make_id', $make->id)
                ->withConfigurations()
                ->with('generations:id,model_id,year_from,year_to')
                ->orderBy('name')
                ->get(['id', 'make_id', 'name']);

            foreach ($models as $model) {
                if ($existing->has($make->id.':'.$model->id)) {
                    continue;
                }

                $name = $make->name.' '.$model->name;
                $years = $model->generations;

                $rows[] = [
                    'make_id' => $make->id,
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
        }

        // Chunked because a full vehicle graph produces thousands of rows and a single insert
        // with that many bindings exceeds what the driver will accept. Every row carries an
        // identical set of columns, which insert() requires of a multi-row batch.
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
