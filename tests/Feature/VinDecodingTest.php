<?php

namespace Tests\Feature;

use App\Catalog\Vehicles\Vin\VinModelHints;
use App\Catalog\Vehicles\Vin\VpicDecodedVehicleMatcher;
use App\Livewire\Storefront\VehicleSelector;
use App\Models\CatalogSource;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\VehicleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A Discovery Sport built in December 2016 for Europe: vPIC answers with the make and the model
 * year 2017, and nothing else. The VIN itself names the model line, and the build is filed under
 * the year before the model year.
 */
class VinDecodingTest extends TestCase
{
    use RefreshDatabase;

    private const VIN = 'SALCA2BN2HH648377';

    private VehicleMake $landRover;

    private VehicleModel $discoverySport;

    private VehicleGeneration $l550;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landRover = VehicleMake::create(['name' => 'Land Rover', 'slug' => 'land-rover', 'is_active' => true]);
        $this->discoverySport = VehicleModel::create(['make_id' => $this->landRover->id, 'name' => 'Discovery Sport', 'slug' => 'discovery-sport', 'is_active' => true]);
        $this->l550 = VehicleGeneration::create(['model_id' => $this->discoverySport->id, 'name' => 'First generation (L550)', 'year_from' => 2014, 'year_to' => 2019]);
    }

    public function test_the_model_line_is_read_off_a_land_rover_vin(): void
    {
        $this->assertSame(['Discovery Sport'], VinModelHints::modelNames(self::VIN));
        $this->assertSame(['Range Rover Evoque', 'Evoque'], VinModelHints::modelNames('SALVA2BG5FH000001'));
        $this->assertSame([], VinModelHints::modelNames('VF1RFB00X12345678'));
    }

    public function test_a_build_filed_under_the_year_before_the_model_year_is_found(): void
    {
        $configuration = VehicleConfiguration::create(['generation_id' => $this->l550->id, 'year' => 2016]);

        $result = app(VpicDecodedVehicleMatcher::class)->match($this->vpicAnswer(), false, self::VIN);

        $this->assertSame('high_confidence', $result->status);
        $this->assertSame($configuration->id, $result->vehicleConfigurationId);
    }

    public function test_without_a_build_for_the_year_the_model_and_generation_are_still_named(): void
    {
        $result = app(VpicDecodedVehicleMatcher::class)->match($this->vpicAnswer(), false, self::VIN);

        $this->assertSame('model_only', $result->status);
        $this->assertSame($this->discoverySport->id, $result->modelId);
        $this->assertSame($this->l550->id, $result->generationId);
    }

    public function test_the_public_api_keeps_answering_from_vpic_alone(): void
    {
        $result = app(VpicDecodedVehicleMatcher::class)->match($this->vpicAnswer(), true, self::VIN);

        $this->assertSame('basic_only', $result->status);
        $this->assertNull($result->modelId);
        $this->assertNull($result->makeId);
    }

    public function test_the_header_selects_the_model_a_vin_names(): void
    {
        $this->connectVpic($this->vpicAnswer());

        Livewire::test(VehicleSelector::class)
            ->set('vin', self::VIN)
            ->call('decodeVin')
            ->assertHasNoErrors()
            ->assertDispatched('vehicle-changed');

        $current = app(VehicleContext::class)->current();

        $this->assertSame($this->discoverySport->id, $current?->modelId);
        $this->assertSame($this->l550->id, $current->generationId);
    }

    /** A VIN that names only the make says so plainly, and starts the dropdowns on the make. */
    public function test_a_vin_naming_only_the_make_says_so_and_picks_the_make(): void
    {
        $this->connectVpic($this->vpicAnswer('SALXA2BN2HH648377'));

        Livewire::test(VehicleSelector::class)
            ->set('vin', 'SALXA2BN2HH648377')
            ->call('decodeVin')
            ->assertSet('makeId', $this->landRover->id)
            ->assertSet('vinMessage', 'Seria ne spune marca, Land Rover, și anul de model, 2017, dar nu și modelul: baza vPIC nu îl are pentru mașinile făcute pentru Europa. Alege modelul din listă; marca e deja aleasă.');

        $this->assertNull(app(VehicleContext::class)->current());
    }

    /** @return array<string, mixed> what vPIC answers for a European Land Rover */
    private function vpicAnswer(string $vin = self::VIN): array
    {
        return [
            'ErrorCode' => '8',
            'ErrorText' => '8 - No detailed data available currently',
            'Make' => 'LAND ROVER',
            'Model' => '',
            'ModelYear' => '2017',
            'VIN' => $vin,
        ];
    }

    /** @param array<string, mixed> $answer */
    private function connectVpic(array $answer): void
    {
        CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'NHTSA vPIC',
            'code' => 'VPIC',
            'source_type' => 'government_vehicle_database',
            'rights_class' => 'public_information',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => true,
            'is_active' => true,
        ]);

        Http::fake(['vpic.nhtsa.dot.gov/*' => Http::response(['Results' => [$answer]])]);
    }
}
