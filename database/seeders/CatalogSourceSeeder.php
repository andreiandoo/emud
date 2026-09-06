<?php

namespace Database\Seeders;

use App\Catalog\Sources\Connectors\EeaVehicleCatalogSourceConnector;
use App\Catalog\Sources\Connectors\LifeOfCapoCatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'code' => 'EEA',
                'name' => 'European Environment Agency vehicle registrations',
                'source_type' => 'open_vehicle_dataset',
                'protocol' => 'http',
                'connector_class' => EeaVehicleCatalogSourceConnector::class,
                'rights_class' => 'open_redistributable',
                'allow_internal' => true,
                'allow_ecommerce' => true,
                'allow_derived' => true,
                'allow_api_redistribution' => true,
                'attribution_required' => true,
                'is_active' => false,
                'license_name' => 'EEA reuse policy / CC BY',
                'license_url' => 'https://www.eea.europa.eu/en/legal-notice',
                'base_url' => 'https://www.eea.europa.eu/en/datahub/datahubitem-view/fa8b1229-3db6-495d-b18e-9c9b3267c02b',
                'field_mapping' => ['external_id' => 'external_id', 'record_type' => 'record_type'],
                'capabilities' => [
                    'vehicles' => true,
                    'passenger_cars' => true,
                    'vans' => true,
                    'eu_type_approval' => true,
                    'eu_tvv' => true,
                    'engine_capacity' => true,
                    'power_kw' => true,
                ],
                'settings' => [
                    'api_url' => 'https://discodata.eea.europa.eu/sql',
                    'release_label' => '2025P',
                    'dataset_published_at' => '2026-06-25',
                    'datasets' => [
                        [
                            'kind' => 'cars',
                            'table' => '[CO2Emission].[latest].[co2cars_2025Pv31]',
                            'year' => 2025,
                            'status' => 'P',
                        ],
                        [
                            'kind' => 'vans',
                            'table' => '[CO2Emission].[latest].[co2vans_2025Pv27]',
                            'year' => 2025,
                            'status' => 'P',
                        ],
                    ],
                    'page_size' => 1000,
                    'auto_canonicalize' => true,
                    'timeout_seconds' => 120,
                    'retry_times' => 4,
                    'retry_sleep_ms' => 2000,
                    'user_agent' => 'eMUD-Automotive-Catalog/1.0 (https://github.com/andreiandoo/emud)',
                ],
            ],
            [
                'code' => 'VPIC',
                'name' => 'NHTSA vPIC',
                'source_type' => 'government_vehicle_database',
                'protocol' => 'database',
                'rights_class' => 'public_information',
                'allow_internal' => true,
                'allow_ecommerce' => true,
                'allow_derived' => true,
                'allow_api_redistribution' => true,
                'attribution_required' => true,
                'is_active' => false,
                'license_name' => 'NHTSA public information / Terms of Use',
                'license_url' => 'https://www.nhtsa.gov/about-nhtsa/terms-use',
                'legal_notes' => 'NHTSA states that information presented on its website is public information and may be distributed or copied. Preserve source attribution and do not imply NHTSA endorsement.',
                'base_url' => 'https://vpic.nhtsa.dot.gov/',
                'capabilities' => [
                    'vehicles' => true,
                    'vin' => true,
                    'wmi' => true,
                    'standalone_vin_decoder' => true,
                    'postgresql_standalone' => true,
                ],
                'settings' => [
                    'resolver_mode' => 'http',
                    'api_base_url' => 'https://vpic.nhtsa.dot.gov',
                    'downloads_url' => 'https://vpic.nhtsa.dot.gov/Downloads',
                    'database_connection' => 'pgsql',
                    'database_schema' => 'vpic',
                    'pg_restore_binary' => 'pg_restore',
                    'download_page_timeout_seconds' => 30,
                    'download_timeout_seconds' => 1800,
                    'restore_timeout_seconds' => 1800,
                    'user_agent' => 'eMUD-Automotive-Catalog/1.0 (https://github.com/andreiandoo/emud)',
                ],
            ],
            [
                'code' => 'LIFEOFCAPO',
                'name' => 'lifeofcapo/car-api',
                'source_type' => 'open_vehicle_taxonomy',
                'protocol' => 'http',
                'connector_class' => LifeOfCapoCatalogSourceConnector::class,
                'rights_class' => 'open_redistributable',
                'allow_internal' => true,
                'allow_ecommerce' => true,
                'allow_derived' => true,
                'allow_api_redistribution' => true,
                'is_active' => false,
                'license_name' => 'MIT',
                'license_url' => 'https://github.com/lifeofcapo/car-api/blob/main/LICENSE',
                'base_url' => 'https://github.com/lifeofcapo/car-api',
                'field_mapping' => ['external_id' => 'external_id', 'record_type' => 'record_type'],
                'settings' => [
                    'repository_api_url' => 'https://api.github.com/repos/lifeofcapo/car-api',
                    'raw_base_url' => 'https://raw.githubusercontent.com/lifeofcapo/car-api',
                    'upstream_ref' => 'main',
                    'brands_file' => 'car-brands.json',
                    'parts_file' => 'car-parts.json',
                    'auto_canonicalize' => true,
                    'user_agent' => 'eMUD-Automotive-Catalog/1.0',
                ],
                'capabilities' => ['vehicles' => true, 'generic_parts' => true],
            ],
            [
                'code' => 'WIKIDATA',
                'name' => 'Wikidata',
                'source_type' => 'open_knowledge_graph',
                'protocol' => 'http',
                'rights_class' => 'open_redistributable',
                'allow_internal' => true,
                'allow_ecommerce' => true,
                'allow_derived' => true,
                'allow_api_redistribution' => true,
                'is_active' => false,
                'license_name' => 'CC0',
                'license_url' => 'https://www.wikidata.org/wiki/Wikidata:Licensing',
                'base_url' => 'https://www.wikidata.org/',
                'settings' => [
                    'action_api_url' => 'https://www.wikidata.org/w/api.php',
                    'user_agent' => 'eMUD-Automotive-Catalog/1.0 (https://github.com/andreiandoo/emud)',
                    'maxlag' => 5,
                    'timeout_seconds' => 30,
                    'search_limit' => 5,
                    'search_language' => 'en',
                    'languages' => ['en', 'ro', 'de', 'fr', 'it', 'es'],
                ],
                'capabilities' => ['aliases' => true, 'vehicle_enrichment' => true, 'wikidata_qid' => true],
            ],
        ];

        foreach ($sources as $payload) {
            $source = CatalogSource::query()->firstOrNew(['code' => $payload['code']]);
            if (! $source->exists) {
                $source->public_id = (string) Str::ulid();
            }
            $source->fill($payload)->save();
        }
    }
}
