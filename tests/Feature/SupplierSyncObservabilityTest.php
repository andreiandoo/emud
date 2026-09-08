<?php

namespace Tests\Feature;

use App\Enums\StockStatus;
use App\Enums\SupplierSyncErrorType;
use App\Enums\SyncStatus;
use App\Jobs\SyncSupplierFeed;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncError;
use App\Models\SupplierSyncRun;
use App\Suppliers\ConnectorRegistry;
use App\Suppliers\Contracts\ReportsFeedIssues;
use App\Suppliers\Contracts\SupplierConnector;
use App\Suppliers\Data\SupplierFeedIssue;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\SupplierCatalogImporter;
use App\Suppliers\SupplierCatalogRetirement;
use App\Suppliers\SupplierFeedGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SupplierSyncObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeObservabilityConnector::$records = [];
        FakeObservabilityConnector::$issues = [];
    }

    public function test_a_row_the_connector_cannot_map_is_recorded_instead_of_vanishing(): void
    {
        $supplier = $this->supplier();
        FakeObservabilityConnector::$records = [$this->record('A1')];
        FakeObservabilityConnector::$issues = [
            new SupplierFeedIssue(SupplierSyncErrorType::Rejected, 'Rândul nu are identificator extern sau denumire.', raw: ['name' => 'fără id']),
        ];

        $this->runSync($supplier);

        $run = SupplierSyncRun::query()->sole();
        $this->assertSame(2, (int) $run->received_count, 'A rejected row is part of what the feed contained.');
        $this->assertSame(1, (int) $run->processed, 'Only the mappable row reached the importer.');
        $this->assertSame(1, (int) $run->rejected_count);
        $this->assertSame(SyncStatus::Completed, $run->status);

        $error = SupplierSyncError::query()->sole();
        $this->assertSame(SupplierSyncErrorType::Rejected, $error->error_type);
        $this->assertSame(['name' => 'fără id'], $error->raw_payload);
        $this->assertSame($supplier->id, $error->supplier_id);
    }

    public function test_one_failing_row_does_not_stop_the_feed_and_is_stored_with_its_payload(): void
    {
        $supplier = $this->supplier();
        FakeObservabilityConnector::$records = [$this->record('A1'), $this->record('BOOM'), $this->record('A3')];

        $this->runSync($supplier, new ThrowingImporter('BOOM', app(\App\Commerce\CurrencyConverter::class)));

        $run = SupplierSyncRun::query()->sole();
        $this->assertSame(3, (int) $run->received_count);
        $this->assertSame(1, (int) $run->failed_count);
        $this->assertSame(SyncStatus::CompletedWithErrors, $run->status);
        $this->assertSame(2, SupplierProduct::query()->count(), 'The rows either side of the failure still imported.');

        $error = SupplierSyncError::query()->sole();
        $this->assertSame(SupplierSyncErrorType::Persistence, $error->error_type);
        $this->assertSame('BOOM', $error->external_identifier);
        $this->assertSame('BOOM', $error->raw_payload['id']);
    }

    public function test_a_collapsed_feed_is_stopped_by_the_guard_and_retires_nothing(): void
    {
        $supplier = $this->supplier();
        $this->baselineRun($supplier, received: 1000);

        $stale = $this->supplierProduct($supplier, 'OLD', now()->subDays(30));
        SupplierOffer::query()->create(['supplier_product_id' => $stale->id, 'stock_status' => StockStatus::InStock->value]);

        FakeObservabilityConnector::$records = [$this->record('A1')];

        $this->runSync($supplier);

        $run = SupplierSyncRun::query()->latest('id')->first();
        $this->assertSame(SyncStatus::AbortedGuard, $run->status);
        $this->assertTrue($run->summary['guard']['tripped']);
        $this->assertSame(1000, $run->summary['guard']['baseline']);

        $this->assertNull($stale->fresh()->discontinued_at, 'A guarded run must never retire anything.');
        $this->assertSame(StockStatus::InStock, $stale->offer()->first()->stock_status);
        $this->assertNull($supplier->fresh()->last_successful_sync_at, 'A guarded run is not a successful sync.');
        $this->assertSame(1, SupplierProduct::query()->where('external_id', 'A1')->count(), 'Rows that did arrive are still kept.');
    }

    public function test_the_guard_stays_out_of_the_way_without_a_baseline(): void
    {
        $supplier = $this->supplier();
        FakeObservabilityConnector::$records = [$this->record('A1')];

        $this->runSync($supplier);

        $run = SupplierSyncRun::query()->sole();
        $this->assertSame(SyncStatus::Completed, $run->status);
        $this->assertSame('no_baseline', $run->summary['guard']['reason']);
        $this->assertNotNull($supplier->fresh()->last_successful_sync_at);
    }

    public function test_a_feed_within_tolerance_completes_and_retires_what_disappeared(): void
    {
        // Lowered so the guard actually compares instead of skipping for lack of a
        // meaningful baseline; the ratio, not the absolute size, is what is tested.
        config(['emud.suppliers.volume_guard.minimum_baseline_records' => 1]);

        $supplier = $this->supplier();
        $this->baselineRun($supplier, received: 2);

        $missing = $this->supplierProduct($supplier, 'GONE', now()->subDays(30));
        SupplierOffer::query()->create(['supplier_product_id' => $missing->id, 'stock_status' => StockStatus::InStock->value, 'stock_quantity' => 4]);

        FakeObservabilityConnector::$records = [$this->record('A1'), $this->record('A2')];

        $this->runSync($supplier);

        $run = SupplierSyncRun::query()->latest('id')->first();
        $this->assertSame(SyncStatus::Completed, $run->status);
        $this->assertSame('within_tolerance', $run->summary['guard']['reason']);
        $this->assertSame(1, (int) $run->retired_count);
        $this->assertNotNull($missing->fresh()->discontinued_at);
        $this->assertSame(StockStatus::Discontinued, $missing->offer()->first()->stock_status);
    }

    public function test_retirement_marks_but_never_deletes_and_spares_recent_products(): void
    {
        $supplier = $this->supplier();

        $missing = $this->supplierProduct($supplier, 'GONE', now()->subDays(30));
        SupplierOffer::query()->create(['supplier_product_id' => $missing->id, 'stock_status' => StockStatus::InStock->value, 'stock_quantity' => 4]);
        $recent = $this->supplierProduct($supplier, 'RECENT', now()->subDay());

        $result = app(SupplierCatalogRetirement::class)->retireMissing($supplier);

        $this->assertSame(1, $result['retired']);
        $this->assertNotNull($missing->fresh()->discontinued_at);
        $this->assertSame(0, (int) $missing->offer()->first()->stock_quantity);
        $this->assertNull($recent->fresh()->discontinued_at, 'A product seen yesterday is not missing.');
        $this->assertDatabaseHas('supplier_products', ['id' => $missing->id]);
    }

    public function test_a_run_that_fails_outright_is_stored_as_a_transport_error(): void
    {
        $supplier = $this->supplier(['allow_internal_data' => false]);

        try {
            $this->runSync($supplier);
            $this->fail('A rights violation must abort the run.');
        } catch (RuntimeException) {
            // expected: the job rethrows so the queue can retry.
        }

        $run = SupplierSyncRun::query()->sole();
        $this->assertSame(SyncStatus::Failed, $run->status);

        $error = SupplierSyncError::query()->sole();
        $this->assertSame(SupplierSyncErrorType::Transport, $error->error_type);
        $this->assertStringContainsString('data rights', $error->message);
    }

    public function test_error_rows_are_capped_so_a_broken_feed_cannot_fill_the_table(): void
    {
        config(['emud.suppliers.max_errors_per_run' => 3]);

        $supplier = $this->supplier();
        FakeObservabilityConnector::$records = [];
        FakeObservabilityConnector::$issues = array_map(
            static fn (int $i): SupplierFeedIssue => new SupplierFeedIssue(SupplierSyncErrorType::Rejected, "Rând invalid {$i}", raw: ['i' => $i]),
            range(1, 10),
        );

        $this->runSync($supplier);

        $run = SupplierSyncRun::query()->sole();
        $this->assertSame(10, (int) $run->rejected_count, 'Every rejected row is still counted.');
        $this->assertSame(3, SupplierSyncError::query()->count(), 'Only the first few are stored.');
        $this->assertSame(10, $run->summary['errors_by_type']['rejected']);
    }

    private function runSync(Supplier $supplier, ?SupplierCatalogImporter $importer = null): void
    {
        (new SyncSupplierFeed($supplier->id, 'catalog'))->handle(
            app(ConnectorRegistry::class),
            $importer ?? app(SupplierCatalogImporter::class),
            app(SupplierFeedGuard::class),
            app(SupplierCatalogRetirement::class),
        );
    }

    private function supplier(array $attributes = []): Supplier
    {
        return Supplier::query()->create([
            'name' => 'Observability Feed',
            'code' => 'OBSERVE',
            'protocol' => 'json',
            'connector_class' => FakeObservabilityConnector::class,
            'catalog_endpoint' => 'https://supplier.test/feed.json',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function supplierProduct(Supplier $supplier, string $externalId, \DateTimeInterface $lastSeen): SupplierProduct
    {
        return SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'external_id' => $externalId,
            'name' => "Produs {$externalId}",
            'last_seen_at' => $lastSeen,
        ]);
    }

    private function baselineRun(Supplier $supplier, int $received): void
    {
        SupplierSyncRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'mode' => 'catalog',
            'status' => SyncStatus::Completed,
            'received_count' => $received,
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);
    }

    private function record(string $id): SupplierRecord
    {
        return new SupplierRecord(
            externalId: $id,
            name: "Produs {$id}",
            sku: $id,
            manufacturerPartNumber: 'MPN-'.$id,
            costPrice: 100.0,
            stockQuantity: 5,
            stockStatus: 'in_stock',
            raw: ['id' => $id, 'name' => "Produs {$id}"],
        );
    }
}

/**
 * Stands in for a real supplier feed so the pipeline can be exercised without
 * credentials, a network or fixture files.
 */
class FakeObservabilityConnector implements ReportsFeedIssues, SupplierConnector
{
    /** @var list<SupplierRecord> */
    public static array $records = [];

    /** @var list<SupplierFeedIssue> */
    public static array $issues = [];

    /** @var list<SupplierFeedIssue> */
    private array $pending = [];

    public function records(Supplier $supplier, string $mode): iterable
    {
        $this->pending = self::$issues;

        foreach (self::$records as $record) {
            yield $record;
        }
    }

    public function takeIssues(): array
    {
        $issues = $this->pending;
        $this->pending = [];

        return $issues;
    }
}

/** Fails on one known identifier so the per-row error path can be exercised. */
class ThrowingImporter extends SupplierCatalogImporter
{
    public function __construct(private readonly string $failOn, \App\Commerce\CurrencyConverter $converter)
    {
        parent::__construct($converter);
    }

    public function import(Supplier $supplier, SupplierRecord $record, string $mode, ?SupplierSyncRun $run = null): array
    {
        if ($record->externalId === $this->failOn) {
            throw new RuntimeException('Simulated write failure.');
        }

        return parent::import($supplier, $record, $mode, $run);
    }
}
