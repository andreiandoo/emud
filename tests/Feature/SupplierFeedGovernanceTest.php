<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Jobs\SyncSupplierFeed;
use App\Models\Supplier;
use App\Models\SupplierFeedArtifact;
use App\Models\SupplierSyncRun;
use App\Suppliers\ConnectorRegistry;
use App\Suppliers\Contracts\SupplierConnector;
use App\Suppliers\Contracts\SupplierFeedArtifactProvider;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\SupplierCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SupplierFeedGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rights_denial_creates_a_failed_audit_run_before_ingestion(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Restricted supplier',
            'code' => 'RESTRICTED',
            'protocol' => 'manual',
            'default_currency' => 'EUR',
            'allow_internal_data' => false,
        ]);

        $registry = Mockery::mock(ConnectorRegistry::class);
        $registry->shouldNotReceive('for');
        $importer = Mockery::mock(SupplierCatalogImporter::class);
        $importer->shouldNotReceive('import');

        try {
            (new SyncSupplierFeed($supplier->id))->handle($registry, $importer);
            $this->fail('Rights-denied supplier sync should throw.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('do not permit internal ingestion', $exception->getMessage());
        }

        $run = SupplierSyncRun::query()->where('supplier_id', $supplier->id)->sole();
        $this->assertSame(SyncStatus::Failed, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertStringContainsString('do not permit internal ingestion', (string) $run->error_message);
    }

    public function test_successful_sync_persists_exact_feed_artifact_provenance(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Permissioned feed',
            'code' => 'PERMISSIONED',
            'protocol' => 'sftp',
            'default_currency' => 'EUR',
            'settings' => ['match_catalog_parts' => false],
            'data_rights_class' => 'permissioned_redistributable',
            'allow_internal_data' => true,
            'allow_ecommerce_data' => true,
        ]);

        $modifiedAt = 1788700000;
        $contents = "sku,name\nA1,Oil Filter\n";
        $connector = new class($modifiedAt, $contents) implements SupplierConnector, SupplierFeedArtifactProvider
        {
            public function __construct(private readonly int $modifiedAt, private readonly string $contents) {}

            public function records(Supplier $supplier, string $mode): iterable
            {
                yield new SupplierRecord(externalId: 'A1', name: 'Oil Filter', sku: 'A1', raw: ['sku' => 'A1']);
            }

            public function lastArtifact(): ?array
            {
                return [
                    'mode' => 'catalog',
                    'source_path' => 'exports/catalog-20260906.csv',
                    'filename' => 'catalog-20260906.csv',
                    'size_bytes' => strlen($this->contents),
                    'source_modified_at' => $this->modifiedAt,
                    'checksum_sha256' => hash('sha256', $this->contents),
                    'retrieved_at' => now(),
                    'metadata' => ['protocol' => 'sftp', 'format' => 'csv'],
                ];
            }
        };

        $registry = Mockery::mock(ConnectorRegistry::class);
        $registry->shouldReceive('for')->once()->andReturn($connector);
        $importer = Mockery::mock(SupplierCatalogImporter::class);
        $importer->shouldReceive('import')->once()->andReturn(['created' => true, 'updated' => false]);

        (new SyncSupplierFeed($supplier->id))->handle($registry, $importer);

        $run = SupplierSyncRun::query()->where('supplier_id', $supplier->id)->sole();
        $this->assertSame(SyncStatus::Completed, $run->status);
        $this->assertSame(1, $run->processed);
        $this->assertSame(1, $run->created_count);

        $artifact = SupplierFeedArtifact::query()->where('supplier_id', $supplier->id)->sole();
        $this->assertSame($run->id, $artifact->supplier_sync_run_id);
        $this->assertSame('exports/catalog-20260906.csv', $artifact->source_path);
        $this->assertSame(hash('sha256', $contents), $artifact->checksum_sha256);
        $this->assertSame('sftp', $artifact->metadata['protocol']);
        $this->assertSame($modifiedAt, $artifact->source_modified_at->timestamp);
    }
}
