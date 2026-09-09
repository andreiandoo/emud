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

    /**
     * Two suppliers listing the same parts on deliberately different terms, because the offer
     * comparison only means something with more than one. The importer maps them onto the same
     * product through brand and MPN, so each part ends up with two offers to rank.
     *
     * The euro supplier is cheaper per unit and charges for dropship and freight; the Romanian
     * one is dearer per unit and ships free. Which one actually wins therefore depends on the
     * part, which is the whole point — and it is why the multiplier below is not the real
     * exchange rate.
     *
     * @var list<array{code: string, name: string, website: string, currency: string, multiplier: float, shipping: float, dropship_fee: float, dispatch: array{0: int, 1: int}, path: string}>
     */
    private const SUPPLIERS = [
        [
            'code' => self::CODE,
            'name' => 'Demo Offroad Supply (date fictive)',
            'website' => 'https://example.com/demo-offroad',
            'currency' => 'EUR',
            'multiplier' => 1.0,
            'shipping' => 18.0,
            'dropship_fee' => 9.0,
            'dispatch' => [3, 6],
            'path' => self::FEED_PATH,
        ],
        [
            'code' => 'DEMO_PARTS_RO',
            'name' => 'Demo Parts România (date fictive)',
            'website' => 'https://example.com/demo-parts-ro',
            'currency' => 'RON',
            'multiplier' => 5.55,
            'shipping' => 0.0,
            'dropship_fee' => 0.0,
            'dispatch' => [1, 2],
            'path' => 'demo/demo-parts-ro-catalog.csv',
        ],
    ];

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
     * `dims` is length/width/height in centimetres. `specs` keys are deliberately the codes from
     * AttributeSeeder: the importer only writes attributes the catalogue already defines, so a
     * made-up key would be silently dropped and the product would look empty again.
     *
     * @var list<array{code: string, category: string, brand: string, name: string, specific: bool, cost: float, rrp: float, weight: float, dims: array{0: float, 1: float, 2: float}, specs: array<string, string>}>
     */
    private const FAMILIES = [
        ['code' => 'SNK', 'category' => 'Snorkele', 'brand' => 'Kestrel 4x4', 'name' => 'Snorkel admisie aer', 'specific' => true, 'cost' => 178.0, 'rrp' => 329.0, 'weight' => 3.4, 'dims' => [120.0, 24.0, 18.0], 'specs' => ['material' => 'Polietilenă rotomulată', 'position' => 'Lateral dreapta']],
        ['code' => 'BUL', 'category' => 'Bare Față', 'brand' => 'Tundra Overland', 'name' => 'Bară față din oțel', 'specific' => true, 'cost' => 742.0, 'rrp' => 1290.0, 'weight' => 48.0, 'dims' => [186.0, 62.0, 44.0], 'specs' => ['material' => 'Oțel 3 mm', 'position' => 'Față']],
        ['code' => 'SLD', 'category' => 'Praguri laterale', 'brand' => 'Tundra Overland', 'name' => 'Praguri laterale întărite', 'specific' => true, 'cost' => 288.0, 'rrp' => 519.0, 'weight' => 22.5, 'dims' => [198.0, 28.0, 22.0], 'specs' => ['material' => 'Oțel 2,5 mm', 'position' => 'Lateral']],
        ['code' => 'SKD', 'category' => 'Scuturi Metalice', 'brand' => 'Axlelock', 'name' => 'Scut motor din aluminiu 6 mm', 'specific' => true, 'cost' => 214.0, 'rrp' => 389.0, 'weight' => 11.2, 'dims' => [92.0, 74.0, 9.0], 'specs' => ['material' => 'Aluminiu 6 mm', 'position' => 'Sub motor']],
        ['code' => 'LFT', 'category' => 'Kit-uri de înălțare', 'brand' => 'Boreal Suspension', 'name' => 'Kit înălțare 50 mm', 'specific' => true, 'cost' => 396.0, 'rrp' => 715.0, 'weight' => 26.0, 'dims' => [64.0, 44.0, 38.0], 'specs' => ['lift_height' => '50', 'axle_load' => '1250']],
        ['code' => 'SHK', 'category' => 'Amortizoare', 'brand' => 'Boreal Suspension', 'name' => 'Set amortizoare off-road', 'specific' => true, 'cost' => 312.0, 'rrp' => 559.0, 'weight' => 14.8, 'dims' => [72.0, 32.0, 26.0], 'specs' => ['lift_height' => '40', 'position' => 'Față și spate']],
        ['code' => 'DIF', 'category' => 'Diferențiale blocabile', 'brand' => 'Axlelock', 'name' => 'Diferențial blocabil pneumatic', 'specific' => true, 'cost' => 964.0, 'rrp' => 1690.0, 'weight' => 9.6, 'dims' => [38.0, 34.0, 24.0], 'specs' => ['axle_load' => '1600', 'position' => 'Punte spate']],
        ['code' => 'MAT', 'category' => 'Covorașe & Tăvițe portbagaj', 'brand' => 'Kestrel 4x4', 'name' => 'Tăviță portbagaj cauciuc', 'specific' => true, 'cost' => 41.0, 'rrp' => 89.0, 'weight' => 2.9, 'dims' => [108.0, 96.0, 6.0], 'specs' => ['material' => 'Cauciuc TPE']],
        ['code' => 'AIR', 'category' => 'Filtre aer', 'brand' => 'Kestrel 4x4', 'name' => 'Filtru aer sport lavabil', 'specific' => true, 'cost' => 34.0, 'rrp' => 79.0, 'weight' => 0.6, 'dims' => [24.0, 19.0, 7.0], 'specs' => ['material' => 'Bumbac uleiat']],
        ['code' => 'LED', 'category' => 'Bare LED', 'brand' => 'Northline Optics', 'name' => 'Bară LED 42" combo 240 W', 'specific' => false, 'cost' => 128.0, 'rrp' => 249.0, 'weight' => 4.1, 'dims' => [107.0, 9.0, 8.0], 'specs' => ['lumens' => '21600', 'voltage' => '12', 'beam_pattern' => 'Combo', 'ip_rating' => 'IP68']],
        ['code' => 'SPT', 'category' => 'Proiectoare', 'brand' => 'Northline Optics', 'name' => 'Set proiectoare LED 7"', 'specific' => false, 'cost' => 96.0, 'rrp' => 189.0, 'weight' => 2.8, 'dims' => [19.0, 19.0, 11.0], 'specs' => ['lumens' => '9800', 'voltage' => '12', 'beam_pattern' => 'Spot', 'ip_rating' => 'IP67']],
        ['code' => 'RTT', 'category' => 'Corturi de acoperiș Hard Top', 'brand' => 'Ridgeline Camp', 'name' => 'Cort de plafon hard-top 2 persoane', 'specific' => false, 'cost' => 1180.0, 'rrp' => 1990.0, 'weight' => 62.0, 'dims' => [212.0, 128.0, 32.0], 'specs' => ['material' => 'ABS și poliester 600D']],
        ['code' => 'RCK', 'category' => 'Portbagaje', 'brand' => 'Ridgeline Camp', 'name' => 'Platformă portbagaj aluminiu', 'specific' => false, 'cost' => 448.0, 'rrp' => 799.0, 'weight' => 19.4, 'dims' => [140.0, 105.0, 14.0], 'specs' => ['material' => 'Aluminiu anodizat', 'position' => 'Plafon']],
        ['code' => 'WHL', 'category' => 'Jante oțel Off-Road', 'brand' => 'Axlelock', 'name' => 'Jantă oțel 16x7 ET-10', 'specific' => false, 'cost' => 62.0, 'rrp' => 129.0, 'weight' => 10.8, 'dims' => [41.0, 41.0, 19.0], 'specs' => ['rim_diameter' => '16', 'wheel_width' => '7', 'wheel_offset' => '-10', 'bolt_pattern' => '5x139.7', 'center_bore' => '108.1']],
    ];

    public function run(): void
    {
        $vehicles = $this->vehicles();

        foreach (self::SUPPLIERS as $index => $profile) {
            $rows = $this->rows($vehicles, $profile);
            Storage::disk('local')->put($profile['path'], $this->csv($rows));
            $this->supplier($profile, createsParts: $index === 0);

            $this->command?->info(sprintf(
                'Furnizor demo %s: %d SKU-uri pentru %d vehicule, în %s.',
                $profile['code'],
                count($rows),
                count($vehicles),
                Storage::disk('local')->path($profile['path']),
            ));
        }

        $codes = implode(' ', array_column(self::SUPPLIERS, 'code'));
        $this->command?->newLine();
        $this->command?->line('Pentru fiecare cod din: '.$codes);
        $this->command?->line('  php artisan suppliers:onboarding-check <COD>');
        $this->command?->line('  php artisan suppliers:sync <COD> --mode=catalog');
        // Feed-created products land in review by design; the shop shows only active ones.
        $this->command?->line('  php artisan suppliers:publish-feed-products <COD>');
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
    private function rows(array $vehicles, array $profile): array
    {
        $rows = [];

        foreach (self::FAMILIES as $family) {
            if ($family['specific']) {
                foreach ($vehicles as $index => $vehicle) {
                    $rows[] = $this->row($family, $profile, $vehicle['label'], $vehicle['slug'], $vehicle['configuration_ids'], $index);
                }

                continue;
            }

            $rows[] = $this->row(
                $family,
                $profile,
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
    private function row(array $family, array $profile, string $vehicleLabel, string $vehicleSlug, array $configurationIds, int $index): array
    {
        // Deliberately not prefixed with the supplier: both feeds must carry the same brand and
        // MPN, or they would map onto two products instead of two offers on one.
        $mpn = sprintf('%s-%s-%s', Str::upper(Str::substr(Str::slug($family['brand']), 0, 3)), $family['code'], $vehicleSlug);
        $sku = 'DEMO-'.$mpn;
        // Prices drift a little per vehicle so that sorting, filtering and margin maths have
        // something other than one repeated number to work on.
        $drift = (1 + ($index % 5) * 0.04) * $profile['multiplier'];

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
            'currency' => $profile['currency'],
            'qty' => (string) (3 + ($index * 7) % 40),
            // Freight and the dropship fee are what make one supplier's cheaper quote the dearer
            // part at the door, so the feed has to carry them or the comparison is decorative.
            'shipping_cost' => number_format($profile['shipping'], 2, '.', ''),
            'dropship_fee' => number_format($profile['dropship_fee'], 2, '.', ''),
            'dispatch_min' => (string) $profile['dispatch'][0],
            'dispatch_max' => (string) $profile['dispatch'][1],
            'weight_kg' => number_format($family['weight'], 2, '.', ''),
            'length_cm' => number_format($family['dims'][0], 1, '.', ''),
            'width_cm' => number_format($family['dims'][1], 1, '.', ''),
            'height_cm' => number_format($family['dims'][2], 1, '.', ''),
            'oem_refs' => implode('|', [$this->reference('OE', $mpn, 1), $this->reference('OE', $mpn, 2)]),
            'cross_refs' => $this->reference('XR', $mpn, 3),
            'fitment' => json_encode(
                array_map(static fn (int $id): array => ['configuration_id' => $id], $configurationIds),
                JSON_THROW_ON_ERROR,
            ),
            'specs' => json_encode(
                $family['specs'] + ['weight_kg' => number_format($family['weight'], 2, '.', ''), 'Garanție' => '24 luni'],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
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

    /** @param array<string, mixed> $profile */
    private function supplier(array $profile, bool $createsParts): Supplier
    {
        $supplier = Supplier::query()->firstOrNew(['code' => $profile['code']]);

        $supplier->fill([
            'name' => $profile['name'],
            'website' => $profile['website'],
            'protocol' => SupplierProtocol::Csv,
            'connector_class' => LocalFileFeedConnector::class,
            'catalog_endpoint' => $profile['path'],
            'default_currency' => $profile['currency'],
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
                'shipping_cost_estimate' => 'shipping_cost',
                'dropship_fee' => 'dropship_fee',
                'dispatch_days_min' => 'dispatch_min',
                'dispatch_days_max' => 'dispatch_max',
                'weight_kg' => 'weight_kg',
                'length_cm' => 'length_cm',
                'width_cm' => 'width_cm',
                'height_cm' => 'height_cm',
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
                // Only the first supplier mints canonical brand+MPN identities. The second one
                // attaches to what the first created, which is the arrangement every supplier
                // after the first should be in — and it is what makes both offers land on one
                // product rather than two.
                'technical_promotion_create_parts' => $createsParts,
                'match_catalog_parts' => true,
                'stale_after_minutes' => 1440,
            ]),
        ]);
        $supplier->save();

        return $supplier;
    }
}
