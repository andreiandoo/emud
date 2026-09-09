<?php

namespace Database\Seeders;

use App\Enums\CatalogRightsClass;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierProtocol;
use App\Models\Supplier;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Suppliers\Connectors\LocalFileFeedConnector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A stand-in supplier, so the shop can be built and exercised before a real feed exists.
 *
 * It writes an actual CSV to disk and configures a supplier to read it, rather than inserting
 * SupplierProduct rows directly. That way `suppliers:sync DEMO_OFFROAD --mode=catalog` runs the
 * genuine path — parser, field mapping, delimiters, offers, matching, technical promotion — and
 * whatever breaks here would have broken on the real AVEX feed too.
 *
 * Brands are invented rather than borrowed from real manufacturers: fabricated prices and part
 * numbers should not end up attached to a real company's name in the catalogue.
 */
class DemoSupplierSeeder extends Seeder
{
    public const CODE = 'DEMO_OFFROAD';

    public const FEED_PATH = 'demo/demo-offroad-catalog.csv';

    /** Makes worth fitting an off-road catalogue to, in the order they are preferred. */
    private const PREFERRED_MAKES = [
        'SUZUKI', 'TOYOTA', 'JEEP', 'LAND ROVER', 'DACIA',
        'FORD', 'MITSUBISHI', 'ISUZU', 'NISSAN', 'VOLKSWAGEN',
    ];

    /**
     * Vehicle-specific families get one SKU per vehicle and fit only that vehicle; universal
     * ones get a single SKU fitted to everything. Categories are the Romanian names from
     * CategorySeeder, because that is what the canonicalizer matches on.
     *
     * @var list<array{code: string, category: string, brand: string, name: string, specific: bool, cost: float, rrp: float, weight: float}>
     */
    private const FAMILIES = [
        ['code' => 'SNK', 'category' => 'Snorkele', 'brand' => 'Kestrel 4x4', 'name' => 'Snorkel admisie aer', 'specific' => true, 'cost' => 178.0, 'rrp' => 329.0, 'weight' => 3.4],
        ['code' => 'BUL', 'category' => 'Bare Față', 'brand' => 'Tundra Overland', 'name' => 'Bară față din oțel', 'specific' => true, 'cost' => 742.0, 'rrp' => 1290.0, 'weight' => 48.0],
        ['code' => 'SLD', 'category' => 'Praguri laterale', 'brand' => 'Tundra Overland', 'name' => 'Praguri laterale întărite', 'specific' => true, 'cost' => 288.0, 'rrp' => 519.0, 'weight' => 22.5],
        ['code' => 'SKD', 'category' => 'Scuturi Metalice', 'brand' => 'Axlelock', 'name' => 'Scut motor din aluminiu 6 mm', 'specific' => true, 'cost' => 214.0, 'rrp' => 389.0, 'weight' => 11.2],
        ['code' => 'LFT', 'category' => 'Kit-uri de înălțare', 'brand' => 'Boreal Suspension', 'name' => 'Kit înălțare 50 mm', 'specific' => true, 'cost' => 396.0, 'rrp' => 715.0, 'weight' => 26.0],
        ['code' => 'SHK', 'category' => 'Amortizoare', 'brand' => 'Boreal Suspension', 'name' => 'Set amortizoare off-road', 'specific' => true, 'cost' => 312.0, 'rrp' => 559.0, 'weight' => 14.8],
        ['code' => 'DIF', 'category' => 'Diferențiale blocabile', 'brand' => 'Axlelock', 'name' => 'Diferențial blocabil pneumatic', 'specific' => true, 'cost' => 964.0, 'rrp' => 1690.0, 'weight' => 9.6],
        ['code' => 'MAT', 'category' => 'Covorașe & Tăvițe portbagaj', 'brand' => 'Kestrel 4x4', 'name' => 'Tăviță portbagaj cauciuc', 'specific' => true, 'cost' => 41.0, 'rrp' => 89.0, 'weight' => 2.9],
        ['code' => 'AIR', 'category' => 'Filtre aer', 'brand' => 'Kestrel 4x4', 'name' => 'Filtru aer sport lavabil', 'specific' => true, 'cost' => 34.0, 'rrp' => 79.0, 'weight' => 0.6],
        ['code' => 'LED', 'category' => 'Bare LED', 'brand' => 'Northline Optics', 'name' => 'Bară LED 42" combo 240 W', 'specific' => false, 'cost' => 128.0, 'rrp' => 249.0, 'weight' => 4.1],
        ['code' => 'SPT', 'category' => 'Proiectoare', 'brand' => 'Northline Optics', 'name' => 'Set proiectoare LED 7"', 'specific' => false, 'cost' => 96.0, 'rrp' => 189.0, 'weight' => 2.8],
        ['code' => 'RTT', 'category' => 'Corturi de acoperiș Hard Top', 'brand' => 'Ridgeline Camp', 'name' => 'Cort de plafon hard-top 2 persoane', 'specific' => false, 'cost' => 1180.0, 'rrp' => 1990.0, 'weight' => 62.0],
        ['code' => 'RCK', 'category' => 'Portbagaje', 'brand' => 'Ridgeline Camp', 'name' => 'Platformă portbagaj aluminiu', 'specific' => false, 'cost' => 448.0, 'rrp' => 799.0, 'weight' => 19.4],
        ['code' => 'WHL', 'category' => 'Jante oțel Off-Road', 'brand' => 'Axlelock', 'name' => 'Jantă oțel 16x7 ET-10', 'specific' => false, 'cost' => 62.0, 'rrp' => 129.0, 'weight' => 10.8],
    ];

    public function run(): void
    {
        $vehicles = $this->vehicles();
        $rows = $this->rows($vehicles);

        Storage::disk('local')->put(self::FEED_PATH, $this->csv($rows));
        $supplier = $this->supplier();

        $this->command?->info(sprintf(
            'Furnizor demo %s: %d SKU-uri pentru %d vehicule, scrise în %s.',
            $supplier->code,
            count($rows),
            count($vehicles),
            Storage::disk('local')->path(self::FEED_PATH),
        ));
        $this->command?->line('1. php artisan suppliers:onboarding-check '.self::CODE);
        $this->command?->line('2. php artisan suppliers:sync '.self::CODE.' --mode=catalog');
        // Feed-created products land in review by design; the shop shows only active ones.
        $this->command?->line('3. php artisan suppliers:publish-feed-products '.self::CODE);
    }

    /**
     * Fitments resolve by configuration id, so the feed has to be built against vehicles that
     * are actually in this database. Without that the parts import cleanly and then show up
     * under no vehicle at all, which is exactly the thing this seeder exists to let you test.
     *
     * @return list<array{label: string, slug: string, configuration_ids: list<int>}>
     */
    private function vehicles(): array
    {
        $rows = DB::table('vehicle_configurations as vc')
            ->join('vehicle_generations as vg', 'vg.id', '=', 'vc.generation_id')
            ->join('vehicle_models as vm', 'vm.id', '=', 'vg.model_id')
            ->join('vehicle_makes as mk', 'mk.id', '=', 'vm.make_id')
            ->whereIn(DB::raw('upper(mk.name)'), self::PREFERRED_MAKES)
            ->select(['vc.id', 'mk.name as make', 'vm.name as model'])
            ->orderBy('mk.name')
            ->orderBy('vm.name')
            ->orderBy('vc.id')
            ->limit(2000)
            ->get();

        if ($rows->isEmpty()) {
            return $this->inventVehicles();
        }

        return $rows
            ->groupBy(fn (object $row): string => $row->make.' '.$row->model)
            ->take(12)
            ->map(fn ($group, string $label): array => [
                'label' => $label,
                'slug' => Str::upper(Str::slug($label)),
                'configuration_ids' => $group->take(3)->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * A fresh install has no vehicle catalogue, and a demo supplier with nothing to fit is not
     * worth seeding. These are marked as demo so they are recognisable next to imported data.
     *
     * @return list<array{label: string, slug: string, configuration_ids: list<int>}>
     */
    private function inventVehicles(): array
    {
        $blueprint = [
            'Suzuki' => ['Jimny', 2020],
            'Toyota' => ['Hilux', 2019],
            'Jeep' => ['Wrangler', 2021],
            'Dacia' => ['Duster', 2022],
        ];
        $vehicles = [];

        foreach ($blueprint as $makeName => [$modelName, $year]) {
            $make = VehicleMake::query()->firstOrCreate(
                ['slug' => Str::slug($makeName)],
                ['name' => $makeName, 'is_active' => true],
            );
            $model = VehicleModel::query()->firstOrCreate(
                ['make_id' => $make->id, 'slug' => Str::slug($modelName)],
                ['name' => $modelName, 'is_active' => true],
            );
            $generation = VehicleGeneration::query()->firstOrCreate(
                ['model_id' => $model->id, 'name' => 'Demo'],
                ['year_from' => $year],
            );
            $configuration = VehicleConfiguration::query()->firstOrCreate(
                ['generation_id' => $generation->id, 'year' => $year],
                ['drive_type' => '4x4'],
            );

            $label = $makeName.' '.$modelName;
            $vehicles[] = [
                'label' => $label,
                'slug' => Str::upper(Str::slug($label)),
                'configuration_ids' => [(int) $configuration->id],
            ];
        }

        return $vehicles;
    }

    /**
     * @param  list<array{label: string, slug: string, configuration_ids: list<int>}>  $vehicles
     * @return list<array<string, string>>
     */
    private function rows(array $vehicles): array
    {
        $rows = [];

        foreach (self::FAMILIES as $family) {
            if ($family['specific']) {
                foreach ($vehicles as $index => $vehicle) {
                    $rows[] = $this->row($family, $vehicle['label'], $vehicle['slug'], $vehicle['configuration_ids'], $index);
                }

                continue;
            }

            $rows[] = $this->row(
                $family,
                'universal',
                'UNIV',
                array_merge(...array_column($vehicles, 'configuration_ids')),
                0,
            );
        }

        return $rows;
    }

    /**
     * @param  array{code: string, category: string, brand: string, name: string, specific: bool, cost: float, rrp: float, weight: float}  $family
     * @param  list<int>  $configurationIds
     * @return array<string, string>
     */
    private function row(array $family, string $vehicleLabel, string $vehicleSlug, array $configurationIds, int $index): array
    {
        $mpn = sprintf('%s-%s-%s', Str::upper(Str::substr(Str::slug($family['brand']), 0, 3)), $family['code'], $vehicleSlug);
        $sku = 'DEMO-'.$mpn;
        // Prices drift a little per vehicle so that sorting, filtering and margin maths have
        // something other than one repeated number to work on.
        $drift = 1 + ($index % 5) * 0.04;

        return [
            'sku' => $sku,
            'title' => $family['specific'] ? $family['name'].' '.$vehicleLabel : $family['name'],
            'manufacturer' => $family['brand'],
            'mpn' => $mpn,
            'barcode' => $this->ean13($mpn),
            'description' => $family['name'].($family['specific'] ? ' dedicat pentru '.$vehicleLabel.'.' : ' universal.').' Produs demonstrativ, date fictive.',
            'category' => $family['category'],
            'dealer_price' => number_format($family['cost'] * $drift, 2, '.', ''),
            'rrp' => number_format($family['rrp'] * $drift, 2, '.', ''),
            'currency' => 'EUR',
            'qty' => (string) (3 + ($index * 7) % 40),
            'weight_kg' => number_format($family['weight'], 2, '.', ''),
            'oem_refs' => implode('|', [$this->reference('OE', $mpn, 1), $this->reference('OE', $mpn, 2)]),
            'cross_refs' => $this->reference('XR', $mpn, 3),
            'fitment' => json_encode(
                array_map(static fn (int $id): array => ['configuration_id' => $id], $configurationIds),
                JSON_THROW_ON_ERROR,
            ),
            'specs' => json_encode([
                'Material' => $family['code'] === 'SKD' ? 'Aluminiu 6 mm' : 'Oțel vopsit în câmp electrostatic',
                'Montaj' => $family['specific'] ? 'Dedicat' : 'Universal',
                'Garanție' => '24 luni',
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'image_urls' => '',
        ];
    }

    /** A stable pseudo-reference, so re-seeding does not churn the cross-reference graph. */
    private function reference(string $prefix, string $mpn, int $salt): string
    {
        return $prefix.'-'.Str::upper(Str::substr(hash('crc32b', $mpn.':'.$salt), 0, 8));
    }

    /** Real check digits, so the EAN matching path is exercised rather than short-circuited. */
    private function ean13(string $seed): string
    {
        $digits = substr(preg_replace('/\D/', '', hash('crc32b', $seed).hash('crc32b', strrev($seed))) ?: '0', 0, 12);
        $digits = str_pad($digits, 12, '0');
        $sum = 0;

        foreach (str_split($digits) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return $digits.((10 - $sum % 10) % 10);
    }

    /** @param list<array<string, string>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($stream, array_values($row));
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    private function supplier(): Supplier
    {
        $supplier = Supplier::query()->firstOrNew(['code' => self::CODE]);

        $supplier->fill([
            'name' => 'Demo Offroad Supply (date fictive)',
            'protocol' => SupplierProtocol::Csv,
            'connector_class' => LocalFileFeedConnector::class,
            'catalog_endpoint' => self::FEED_PATH,
            'default_currency' => 'EUR',
            'timezone' => 'Europe/Bucharest',
            'priority' => 50,
            'data_rights_class' => CatalogRightsClass::PermissionedRedistributable,
            'allow_internal_data' => true,
            'allow_ecommerce_data' => true,
            'allow_derived_data' => true,
            'allow_api_redistribution' => false,
            'attribution_required' => false,
            'onboarding_status' => SupplierOnboardingStatus::Live,
            'is_active' => true,
            'field_mapping' => [
                'external_id' => 'sku',
                'name' => 'title',
                'sku' => 'sku',
                'brand' => 'manufacturer',
                'manufacturer_part_number' => 'mpn',
                'ean' => 'barcode',
                'description' => 'description',
                'category_external_id' => 'category',
                'cost_price' => 'dealer_price',
                'recommended_retail_price' => 'rrp',
                'currency' => 'currency',
                'stock_quantity' => 'qty',
                'weight_kg' => 'weight_kg',
                'oe_numbers' => 'oem_refs',
                'cross_references' => 'cross_refs',
                'fitments' => 'fitment',
                'attributes' => 'specs',
                'images' => 'image_urls',
            ],
            'settings' => array_replace($supplier->settings ?? [], [
                'feed_disk' => 'local',
                'feed_format' => 'csv',
                'delimiter' => ',',
                'oe_numbers_delimiter' => '|',
                'cross_references_delimiter' => '|',
                'images_delimiter' => '|',
                // The storefront sells Products, not CatalogParts, and nothing else creates
                // them here. Without this the import succeeds and the shop stays empty, which
                // defeats the point of a stand-in supplier.
                'auto_create_products' => true,
                'technical_promotion_enabled' => true,
                // This is the first supplier in an empty catalogue, so it has to be the one
                // allowed to mint canonical brand+MPN identities. Later suppliers attach to
                // what it created instead of inventing their own.
                'technical_promotion_create_parts' => true,
                'match_catalog_parts' => true,
                'stale_after_minutes' => 1440,
            ]),
        ]);
        $supplier->save();

        return $supplier;
    }
}
