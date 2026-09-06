<?php

namespace Database\Seeders;

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
                'rights_class' => 'open_redistributable',
                'allow_internal' => true,
                'allow_ecommerce' => true,
                'allow_derived' => true,
                'allow_api_redistribution' => true,
                'attribution_required' => true,
                'is_active' => false,
                'license_url' => 'https://www.eea.europa.eu/en/legal-notice',
                'capabilities' => ['vehicles' => true, 'eu_tvv' => true],
                'settings' => ['format' => 'csv'],
            ],
            [
                'code' => 'VPIC',
                'name' => 'NHTSA vPIC',
                'source_type' => 'government_vehicle_database',
                'protocol' => 'database',
                'rights_class' => 'unknown_pending_review',
                'allow_internal' => true,
                'is_active' => false,
                'base_url' => 'https://vpic.nhtsa.dot.gov/',
                'capabilities' => ['vehicles' => true, 'vin' => true, 'wmi' => true],
            ],
            [
                'code' => 'LIFEOFCAPO',
                'name' => 'lifeofcapo/car-api',
                'source_type' => 'open_vehicle_taxonomy',
                'protocol' => 'http',
                'rights_class' => 'open_redistributable',
                'allow_internal' => true,
                'allow_ecommerce' => true,
                'allow_derived' => true,
                'allow_api_redistribution' => true,
                'is_active' => false,
                'license_name' => 'MIT',
                'base_url' => 'https://github.com/lifeofcapo/car-api',
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
                'base_url' => 'https://www.wikidata.org/',
                'capabilities' => ['aliases' => true, 'vehicle_enrichment' => true],
            ],
        ];

        foreach ($sources as $source) {
            CatalogSource::query()->updateOrCreate(
                ['code' => $source['code']],
                ['public_id' => (string) Str::ulid(), ...$source],
            );
        }
    }
}
