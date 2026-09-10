<?php

namespace Tests\Feature;

use App\Catalog\Matching\BrandResolver;
use App\Catalog\Matching\SupplierCatalogPartMatcher;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Enums\SyncStatus;
use App\Jobs\MatchSupplierProductsToCatalog;
use App\Models\Brand;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMatchCandidate;
use App\Models\SupplierSyncRun;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\SupplierCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierCatalogMatchingTest extends TestCase
{
    use RefreshDatabase;

    private const EAN = '4006381333931';

    public function test_a_gtin_held_by_one_part_maps_automatically(): void
    {
        $part = $this->part('Bosch', '0 986 494 123', [['EAN_GTIN', self::EAN]]);
        $article = $this->article(['ean' => self::EAN]);

        $result = $this->matcher()->match($article);

        $this->assertSame('mapped_auto', $result['status']);
        $this->assertSame($part->id, $article->fresh()->catalog_part_id);
        $this->assertContains('exact_gtin', $result['reasons']);
    }

    public function test_upc_and_ean_forms_of_one_barcode_are_the_same_identity(): void
    {
        $part = $this->part('Bosch', 'UPC-1', [['UPC', '036000291452']]);
        $article = $this->article(['ean' => '0036000291452']);

        $this->assertSame('mapped_auto', $this->matcher()->match($article)['status']);
        $this->assertSame($part->id, $article->fresh()->catalog_part_id);
    }

    public function test_a_placeholder_barcode_never_matches_anything(): void
    {
        $this->part('Bosch', 'PLACEHOLDER-1', [['EAN_GTIN', '0000000000000']]);
        $article = $this->article(['ean' => '0000000000000']);

        $result = $this->matcher()->match($article);

        $this->assertSame('unmatched', $result['status']);
        $this->assertTrue($result['invalid_gtin']);
        $this->assertSame('0000000000000', $article->fresh()->catalog_mapping_reason['invalid_gtin']);
    }

    public function test_a_barcode_held_by_two_parts_is_not_an_identity(): void
    {
        $this->part('Bosch', 'SHARED-1', [['EAN_GTIN', self::EAN]]);
        $this->part('Bosch', 'SHARED-2', [['EAN_GTIN', self::EAN]]);
        $article = $this->article(['ean' => self::EAN]);

        $result = $this->matcher()->match($article);

        $this->assertSame('candidate', $result['status']);
        $this->assertNull($article->fresh()->catalog_part_id);
        $this->assertSame(['shared_gtin'], $result['reasons']);
    }

    public function test_brand_and_mpn_map_automatically_whatever_the_punctuation(): void
    {
        $part = $this->part('Bosch', '0 986 494 123');
        $article = $this->article(['raw_brand' => 'BOSCH', 'manufacturer_part_number' => '0986494123']);

        $result = $this->matcher()->match($article);

        $this->assertSame('mapped_auto', $result['status']);
        $this->assertSame($part->id, $article->fresh()->catalog_part_id);
        $this->assertSame(['exact_brand_mpn'], $result['reasons']);
    }

    public function test_the_same_number_from_another_manufacturer_is_not_offered_as_a_match(): void
    {
        $this->part('Bosch', 'W712');
        $this->brand('MANN');
        $article = $this->article(['raw_brand' => 'MANN', 'manufacturer_part_number' => 'W712']);

        $result = $this->matcher()->match($article);

        $this->assertSame('candidate', $result['status']);
        $this->assertSame(55.0, (float) $article->fresh()->mapping_confidence);
        $this->assertSame(['mpn_brand_mismatch'], $result['reasons']);
    }

    public function test_an_alias_turns_an_unknown_brand_spelling_into_a_confirmed_one(): void
    {
        $part = $this->part('febi bilstein', '01234');
        $article = $this->article(['raw_brand' => 'FEBI', 'manufacturer_part_number' => '01234']);

        $this->assertSame('candidate', $this->matcher()->match($article)['status'], 'An unknown brand caps an MPN match below automatic.');

        app(BrandResolver::class)->alias('FEBI', $part->brand_id);
        $result = $this->matcher()->match($article->fresh());

        $this->assertSame('mapped_auto', $result['status']);
        $this->assertSame(['exact_brand_mpn', 'brand_alias'], $result['reasons']);
    }

    public function test_a_reference_shared_with_another_manufacturer_is_never_offered(): void
    {
        // The catalogue holds the MANN filter; the supplier sells the Filtron one that
        // fits the same engine. Same OE number, different product.
        $this->part('MANN', 'W 712/75', [['OE', '030 115 561 AN']]);
        $this->brand('Filtron');
        $article = $this->article([
            'raw_brand' => 'Filtron',
            'manufacturer_part_number' => 'OP 526',
            'technical_payload' => ['oe_numbers' => ['030115561AN']],
        ]);

        $result = $this->matcher()->match($article);

        $this->assertSame('unmatched', $result['status']);
        $this->assertSame(0, SupplierProductMatchCandidate::query()->count());
    }

    public function test_a_shared_reference_from_the_same_brand_is_a_strong_candidate_but_never_automatic(): void
    {
        $part = $this->part('Filtron', 'OP 526', [['OE', '030 115 561 AN']]);
        $article = $this->article([
            'raw_brand' => 'Filtron',
            'manufacturer_part_number' => 'OP-526/1',
            'technical_payload' => ['oe_numbers' => ['030115561AN']],
        ]);

        $result = $this->matcher()->match($article);

        $this->assertSame('candidate', $result['status']);
        $this->assertSame(['brand_and_reference'], $result['reasons']);
        $this->assertSame($part->id, SupplierProductMatchCandidate::query()->sole()->catalog_part_id);
    }

    public function test_identifiers_that_disagree_are_flagged_rather_than_resolved(): void
    {
        $byBarcode = $this->part('Bosch', 'AAA1', [['EAN_GTIN', self::EAN]]);
        $byNumber = $this->part('Bosch', 'BBB2');
        $article = $this->article(['raw_brand' => 'Bosch', 'manufacturer_part_number' => 'BBB2', 'ean' => self::EAN]);

        $result = $this->matcher()->match($article);

        $this->assertSame('conflict', $result['status']);
        $this->assertNull($article->fresh()->catalog_part_id);
        $this->assertEqualsCanonicalizing([$byBarcode->id, $byNumber->id], $article->fresh()->catalog_mapping_reason['conflicting_parts']);
    }

    public function test_a_rejected_candidate_stays_rejected_after_rematching(): void
    {
        $this->part('Bosch', 'Z9');
        $article = $this->article(['manufacturer_part_number' => 'Z9']);

        $this->matcher()->match($article);
        SupplierProductMatchCandidate::query()->sole()->update(['status' => 'rejected']);

        $result = $this->matcher()->match($article->fresh());

        $this->assertSame('unmatched', $result['status']);
        $this->assertSame('rejected', SupplierProductMatchCandidate::query()->sole()->status);
    }

    public function test_a_manual_mapping_is_never_overwritten(): void
    {
        $this->part('Bosch', 'AUTO-1', [['EAN_GTIN', self::EAN]]);
        $chosen = $this->part('Bosch', 'CHOSEN-1');
        $article = $this->article(['ean' => self::EAN, 'catalog_part_id' => $chosen->id, 'catalog_mapping_status' => 'mapped_manual']);

        $this->assertSame('mapped_manual', $this->matcher()->match($article)['status']);
        $this->assertSame($chosen->id, $article->fresh()->catalog_part_id);
    }

    public function test_a_prefix_configured_for_the_supplier_is_stripped_before_matching(): void
    {
        $part = $this->part('Bosch', '0 986 494 123');
        $article = $this->article(
            ['raw_brand' => 'Bosch', 'manufacturer_part_number' => 'BOS-0986494123'],
            $this->supplier(['mpn_strip_prefixes' => ['BOS-']]),
        );

        $this->assertSame('mapped_auto', $this->matcher()->match($article)['status']);
        $this->assertSame($part->id, $article->fresh()->catalog_part_id);
    }

    public function test_import_records_every_identifier_once_and_replaces_them_when_the_row_changes(): void
    {
        $supplier = $this->supplier();
        $importer = app(SupplierCatalogImporter::class);

        $record = fn (array $oe, int $version): SupplierRecord => new SupplierRecord(
            externalId: 'IMP-1',
            name: 'Filtru ulei',
            ean: self::EAN,
            manufacturerPartNumber: 'OP 526',
            brand: 'Filtron',
            oeNumbers: $oe,
            crossReferences: [['brand' => 'MANN', 'number' => 'W 712/75']],
            raw: ['id' => 'IMP-1', 'version' => $version],
        );

        // The same OE number spelled two ways is one identity.
        $importer->import($supplier, $record(['030 115 561 AN', '030115561AN'], 1), 'catalog');
        $article = SupplierProduct::query()->sole();
        $this->assertEqualsCanonicalizing(['GTIN', 'MPN', 'OE', 'CROSS_REFERENCE'], $article->identifiers()->get()->map(fn ($row) => $row->type->value)->all());
        $this->assertSame('MANN', $article->identifiers()->where('type', 'CROSS_REFERENCE')->sole()->brand);

        $importer->import($supplier, $record([], 2), 'catalog');
        $this->assertSame(0, $article->identifiers()->where('type', 'OE')->count(), 'A number the supplier dropped must stop matching.');
        $this->assertSame(3, $article->identifiers()->count());
    }

    public function test_the_matching_job_writes_its_outcome_onto_the_sync_run(): void
    {
        $supplier = $this->supplier();
        $run = SupplierSyncRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'mode' => 'catalog',
            'status' => SyncStatus::Completed,
            'summary' => ['records' => ['received_count' => 2]],
        ]);

        $this->part('Bosch', 'JOB-1', [['EAN_GTIN', self::EAN]]);
        $this->article(['ean' => self::EAN, 'last_supplier_sync_run_id' => $run->id], $supplier);
        $this->article(['ean' => '0000000000000', 'last_supplier_sync_run_id' => $run->id], $supplier);

        (new MatchSupplierProductsToCatalog($supplier->id, 100, $run->id))->handle($this->matcher());

        $matching = $run->fresh()->summary['matching'];
        $this->assertSame(2, $matching['processed']);
        $this->assertSame(1, $matching['by_status']['mapped_auto']);
        $this->assertSame(1, $matching['by_status']['unmatched']);
        $this->assertSame(1, $matching['invalid_gtins']);
        $this->assertSame(2, $run->fresh()->summary['records']['received_count'], 'The feed counters written by the sync must survive.');
    }

    private function matcher(): SupplierCatalogPartMatcher
    {
        // Fresh each time: the brand resolver memoises, and a test that adds an alias
        // must see it on the next match exactly as a later queue job would.
        return app(SupplierCatalogPartMatcher::class);
    }

    private function supplier(array $settings = []): Supplier
    {
        return Supplier::query()->firstOrCreate(
            ['code' => $settings === [] ? 'MATCH' : 'MATCH-'.md5(json_encode($settings))],
            ['name' => 'Matching Feed', 'protocol' => 'json', 'is_active' => false, 'settings' => $settings],
        );
    }

    /** @param array<string, mixed> $attributes */
    private function article(array $attributes, ?Supplier $supplier = null): SupplierProduct
    {
        return SupplierProduct::query()->create([
            'supplier_id' => ($supplier ?? $this->supplier())->id,
            'external_id' => 'ART-'.Str::random(8),
            'name' => 'Articol furnizor',
            ...$attributes,
        ]);
    }

    private function brand(string $name): Brand
    {
        return Brand::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
    }

    /** @param list<array{0: string, 1: string}> $numbers Extra [scheme, number] pairs. */
    private function part(string $brand, string $mpn, array $numbers = []): CatalogPart
    {
        $normalizer = app(IdentifierNormalizer::class);
        $brandModel = $this->brand($brand);

        $part = CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'brand_id' => $brandModel->id,
            'mpn_raw' => $mpn,
            'mpn_normalized' => $normalizer->normalize($mpn),
        ]);

        foreach ([['MPN', $mpn], ...$numbers] as [$scheme, $number]) {
            CatalogPartNumber::query()->create([
                'catalog_part_id' => $part->id,
                'brand_id' => $scheme === 'MPN' ? $brandModel->id : null,
                'scheme' => $scheme,
                'number_raw' => $number,
                'number_normalized' => $normalizer->normalize($number),
                'number_compact' => $normalizer->compact($number),
            ]);
        }

        return $part;
    }
}
