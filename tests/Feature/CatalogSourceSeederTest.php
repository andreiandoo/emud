<?php

namespace Tests\Feature;

use App\Models\CatalogSource;
use Database\Seeders\CatalogSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSourceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_installs_open_vehicle_source_profiles_disabled_by_default(): void
    {
        $this->seed(CatalogSourceSeeder::class);

        foreach (['EEA', 'VPIC', 'LIFEOFCAPO', 'WIKIDATA'] as $code) {
            $source = CatalogSource::query()->where('code', $code)->sole();

            $this->assertFalse($source->is_active, "{$code} must be seeded inactive.");
            $this->assertNotEmpty($source->public_id);
        }
    }

    public function test_reseeding_preserves_operator_activation_and_configuration(): void
    {
        $this->seed(CatalogSourceSeeder::class);

        $source = CatalogSource::query()->where('code', 'LIFEOFCAPO')->sole();
        $source->update([
            'is_active' => true,
            'settings' => ['upstream_ref' => 'v2.0.0'] + $source->settings,
            'field_mapping' => ['external_id' => 'custom_id'],
            'allow_api_redistribution' => false,
            'attribution_required' => true,
        ]);
        $publicId = $source->public_id;

        $this->seed(CatalogSourceSeeder::class);

        $source->refresh();

        $this->assertTrue($source->is_active, 'Re-seeding must not deactivate a configured source.');
        $this->assertSame('v2.0.0', $source->settings['upstream_ref']);
        $this->assertSame(['external_id' => 'custom_id'], $source->field_mapping);
        $this->assertFalse($source->allow_api_redistribution);
        $this->assertTrue($source->attribution_required);
        $this->assertSame($publicId, $source->public_id);
        $this->assertSame(1, CatalogSource::query()->where('code', 'LIFEOFCAPO')->count());
    }

    public function test_reseeding_refreshes_profile_owned_fields(): void
    {
        $this->seed(CatalogSourceSeeder::class);

        $source = CatalogSource::query()->where('code', 'EEA')->sole();
        $source->update([
            'name' => 'Stale name',
            'connector_class' => 'App\\Obsolete\\Connector',
            'capabilities' => ['vehicles' => false],
        ]);

        $this->seed(CatalogSourceSeeder::class);

        $source->refresh();

        $this->assertSame('European Environment Agency vehicle registrations', $source->name);
        $this->assertStringContainsString('EeaVehicleCatalogSourceConnector', $source->connector_class);
        $this->assertTrue($source->capabilities['vehicles']);
    }

    public function test_reseeding_adds_missing_settings_keys_without_discarding_operator_values(): void
    {
        $this->seed(CatalogSourceSeeder::class);

        $source = CatalogSource::query()->where('code', 'EEA')->sole();
        $settings = $source->settings;
        // Read from the profile rather than written here: the default is tuned against
        // Discodata's timeout (1000, then 5000 once the crawl was split by manufacturer),
        // and what this test guards is that the current default arrives, not its value.
        $profileDefault = $settings['page_size'];
        unset($settings['page_size']);
        $settings['timeout_seconds'] = 600;
        $source->update(['settings' => $settings]);

        $this->seed(CatalogSourceSeeder::class);

        $source->refresh();

        $this->assertSame($profileDefault, $source->settings['page_size'], 'New profile defaults must still reach existing sources.');
        $this->assertSame(600, $source->settings['timeout_seconds'], 'Operator overrides must survive re-seeding.');
    }
}
