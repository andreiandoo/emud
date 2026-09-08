<?php

namespace Tests\Feature;

use App\Catalog\Canonicalization\CanonicalizationResult;
use App\Catalog\Canonicalization\CatalogCanonicalizerRegistry;
use App\Catalog\Canonicalization\Contracts\CatalogRecordCanonicalizer;
use App\Jobs\CanonicalizeCatalogSourceRecords;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CatalogCanonicalizationBatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Canonicalizing a record moves it out of the very statuses the query selects. With offset
     * paging that shrinking result set silently steps over one page for every page published,
     * so a source finished "successfully" with roughly half its records untouched — which is
     * how EEA and lifeofcapo both ended up part-canonicalized with a clean run status.
     */
    public function test_it_canonicalizes_every_staged_record_not_only_alternate_pages(): void
    {
        Queue::fake();
        $source = $this->source(PublishingCanonicalizer::class);
        $this->stage($source, 1_200);

        (new CanonicalizeCatalogSourceRecords($source->id))->handle(app(CatalogCanonicalizerRegistry::class));

        $this->assertSame(0, CatalogSourceRecord::query()->where('mapping_status', 'unprocessed')->count());
        $this->assertSame(1_200, CatalogSourceRecord::query()->where('mapping_status', 'published')->count());
    }

    /**
     * The limit is a batch size. A source with more staged records than one batch has to queue
     * the next one itself, or it stops half-done and waits for a human to notice.
     */
    public function test_a_batch_that_hits_its_limit_queues_the_next_one(): void
    {
        Queue::fake();
        $source = $this->source(PublishingCanonicalizer::class);
        $this->stage($source, 700);

        (new CanonicalizeCatalogSourceRecords($source->id, 600))->handle(app(CatalogCanonicalizerRegistry::class));

        $this->assertSame(600, CatalogSourceRecord::query()->where('mapping_status', 'published')->count());
        Queue::assertPushed(
            CanonicalizeCatalogSourceRecords::class,
            fn (CanonicalizeCatalogSourceRecords $job): bool => $job->sourceId === $source->id && $job->limit === 600,
        );
    }

    /**
     * A record that fails keeps matching the query, so chaining on "the batch was full" alone
     * would requeue the same failures forever.
     */
    public function test_a_batch_that_only_fails_does_not_queue_another(): void
    {
        Queue::fake();
        $source = $this->source(FailingCanonicalizer::class);
        $this->stage($source, 700);

        (new CanonicalizeCatalogSourceRecords($source->id, 600))->handle(app(CatalogCanonicalizerRegistry::class));

        $this->assertSame(600, CatalogSourceRecord::query()->where('mapping_status', 'failed')->count());
        Queue::assertNotPushed(CanonicalizeCatalogSourceRecords::class);
    }

    private function source(string $canonicalizer): CatalogSource
    {
        return CatalogSource::query()->create([
            'public_id' => '01BATCHSOURCE0000000000000',
            'name' => 'Batching test source',
            'code' => 'BATCHING',
            'source_type' => 'test',
            'protocol' => 'http',
            'canonicalizer_class' => $canonicalizer,
            'rights_class' => 'internal_reference',
            'allow_internal' => true,
            'is_active' => true,
        ]);
    }

    private function stage(CatalogSource $source, int $count): void
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'catalog_source_id' => $source->id,
                'record_type' => 'vehicle_configuration',
                'external_id' => "batch:{$i}",
                'raw_payload' => json_encode(['n' => $i]),
                'mapping_status' => 'unprocessed',
                'deleted_at_source' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('catalog_source_records')->insert($chunk);
        }
    }
}

class PublishingCanonicalizer implements CatalogRecordCanonicalizer
{
    public function canonicalize(CatalogSourceRecord $record): CanonicalizationResult
    {
        return CanonicalizationResult::published('vehicle_configuration', $record->id);
    }
}

class FailingCanonicalizer implements CatalogRecordCanonicalizer
{
    public function canonicalize(CatalogSourceRecord $record): CanonicalizationResult
    {
        throw new \RuntimeException('Canonicalization is broken for this record.');
    }
}
