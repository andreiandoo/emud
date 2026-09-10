<?php

namespace Tests\Feature;

use App\Livewire\Storefront\VehicleFinder;
use App\Models\VehicleCollection;
use App\Models\VehicleConfiguration;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\VehicleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The three ways into a collection: pick the car, decode a VIN, choose a derivative.
 *
 * The VIN path is exercised without touching the network. vPIC is a catalog source like any
 * other, and with none enabled the resolver says so before it opens a socket — which is also
 * exactly what a visitor sees on a shop that has not connected it.
 */
class VehicleFinderTest extends TestCase
{
    use RefreshDatabase;

    private VehicleMake $dacia;

    private VehicleModel $duster;

    private VehicleGeneration $second;

    private VehicleCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dacia = VehicleMake::create(['name' => 'Dacia', 'slug' => 'dacia', 'is_active' => true]);
        $this->duster = VehicleModel::create(['make_id' => $this->dacia->id, 'name' => 'Duster', 'slug' => 'duster', 'is_active' => true]);
        $this->second = VehicleGeneration::create(['model_id' => $this->duster->id, 'name' => 'II', 'year_from' => 2018, 'year_to' => 2024]);

        VehicleConfiguration::create(['generation_id' => $this->second->id, 'year' => 2019]);
        VehicleConfiguration::create(['generation_id' => $this->second->id, 'year' => 2021]);

        $this->collection = VehicleCollection::create([
            'name' => 'Dacia', 'slug' => 'dacia',
            'make_id' => $this->dacia->id, 'is_active' => true,
        ]);
    }

    /** Someone already looking at Dacia should not be asked which manufacturer they meant. */
    public function test_the_picker_starts_on_the_collections_make(): void
    {
        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->assertSet('pickMakeId', (string) $this->dacia->id);
    }

    public function test_the_years_offered_are_the_ones_the_make_actually_has(): void
    {
        $years = Livewire::test(VehicleFinder::class, ['collection' => $this->collection])->viewData('years');

        $this->assertSame([2021, 2019], $years);
    }

    public function test_choosing_a_year_narrows_the_models(): void
    {
        $other = VehicleModel::create(['make_id' => $this->dacia->id, 'name' => 'Logan', 'slug' => 'logan', 'is_active' => true]);
        $generation = VehicleGeneration::create(['model_id' => $other->id, 'name' => 'III', 'year_from' => 2020]);
        VehicleConfiguration::create(['generation_id' => $generation->id, 'year' => 2021]);

        $component = Livewire::test(VehicleFinder::class, ['collection' => $this->collection]);

        $this->assertEqualsCanonicalizing(
            ['Duster', 'Logan'],
            $component->viewData('models')->pluck('name')->all(),
        );

        $component->set('pickYear', '2019');

        $this->assertSame(['Duster'], $component->viewData('models')->pluck('name')->all());
    }

    /** A year that no longer contains the chosen model must not leave a stale selection behind. */
    public function test_a_year_that_excludes_the_chosen_model_clears_it(): void
    {
        $other = VehicleModel::create(['make_id' => $this->dacia->id, 'name' => 'Logan', 'slug' => 'logan', 'is_active' => true]);
        $generation = VehicleGeneration::create(['model_id' => $other->id, 'name' => 'III', 'year_from' => 2020]);
        VehicleConfiguration::create(['generation_id' => $generation->id, 'year' => 2021]);

        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->set('pickModelId', (string) $other->id)
            ->set('pickYear', '2019')
            ->assertSet('pickModelId', '');
    }

    public function test_picking_a_car_filters_the_shop_for_it(): void
    {
        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->set('pickModelId', (string) $this->duster->id)
            ->set('pickGenerationId', (string) $this->second->id)
            ->set('pickYear', '2019')
            ->call('applyPick')
            ->assertHasNoErrors()
            ->assertDispatched('vehicle-changed')
            ->assertDispatched('vehicle-picked');

        $selected = app(VehicleContext::class)->current();

        $this->assertSame($this->duster->id, $selected->modelId);
        $this->assertSame($this->second->id, $selected->generationId);
        $this->assertSame(2019, $selected->year);
    }

    /** The submodel is optional: plenty of owners have no idea which generation they drive. */
    public function test_the_submodel_may_be_left_blank(): void
    {
        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->set('pickModelId', (string) $this->duster->id)
            ->call('applyPick')
            ->assertHasNoErrors();

        $this->assertNull(app(VehicleContext::class)->current()->generationId);
    }

    /** The ids come from the client, so the cascade is re-checked rather than trusted. */
    public function test_a_model_from_another_make_is_refused(): void
    {
        $other = VehicleMake::create(['name' => 'Suzuki', 'slug' => 'suzuki', 'is_active' => true]);
        $jimny = VehicleModel::create(['make_id' => $other->id, 'name' => 'Jimny', 'slug' => 'jimny', 'is_active' => true]);

        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->set('pickModelId', (string) $jimny->id)
            ->call('applyPick')
            ->assertHasErrors('pickModelId');

        $this->assertNull(app(VehicleContext::class)->current());
    }

    public function test_a_malformed_vin_is_refused_before_anything_is_looked_up(): void
    {
        // I, O and Q are not VIN characters, which is the whole reason they were excluded.
        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->set('vin', 'VF1RFB00X1234567O')
            ->call('decodeVin')
            ->assertHasErrors('vin');
    }

    public function test_a_vin_lookup_says_so_plainly_when_the_decoder_is_not_connected(): void
    {
        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->set('vin', 'VF1RFB00X12345678')
            ->call('decodeVin')
            ->assertHasNoErrors()
            ->assertSet('vinMessage', 'Căutarea după serie nu este disponibilă acum. Alege mașina din listă.');
    }

    /** A candidate the customer picks carries the exact build, which dropdowns cannot supply. */
    public function test_choosing_a_decoded_candidate_selects_that_exact_build(): void
    {
        $engine = VehicleEngine::create(['generation_id' => $this->second->id, 'name' => '1.5 dCi']);
        $configuration = VehicleConfiguration::create([
            'generation_id' => $this->second->id, 'engine_id' => $engine->id, 'year' => 2020,
        ]);

        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->call('chooseCandidate', $configuration->id)
            ->assertDispatched('vehicle-changed');

        $selected = app(VehicleContext::class)->current();

        $this->assertSame($configuration->id, $selected->configurationId);
        $this->assertSame(2020, $selected->year);
    }

    public function test_the_third_button_is_absent_where_there_are_no_derivatives(): void
    {
        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->assertDontSee('Alege din modele')
            ->assertSee('Alege mașina ta')
            ->assertSee('Caută după VIN');

        VehicleCollection::create([
            'name' => 'Dacia Duster', 'slug' => 'dacia-duster',
            'parent_id' => $this->collection->id, 'make_id' => $this->dacia->id,
            'model_id' => $this->duster->id, 'is_active' => true,
        ]);

        Livewire::test(VehicleFinder::class, ['collection' => $this->collection])
            ->assertSee('Alege din modele')
            ->assertSee('Dacia Duster');
    }
}
