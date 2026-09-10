<?php

namespace Tests\Feature\Workshops\Concerns;

use App\Models\Workshop;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\RecordNormalizers;
use App\Workshops\Ingestion\SourceRecordStore;
use App\Workshops\Sources\Rar\RarRecordKeys;
use App\Workshops\Support\RomanianCounties;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

trait BuildsWorkshops
{
    protected function quietWorkshopSources(): void
    {
        Sleep::fake();
        config([
            'workshops.rar.request_delay_ms' => 0,
            'workshops.rar.retry_base_delay_ms' => 0,
            'workshops.rar.max_retries' => 2,
            'workshops.web.request_delay_ms' => 0,
            'workshops.web.search_delay_ms' => 0,
            'workshops.geocoder.request_delay_ms' => 0,
        ]);
    }

    protected function fixture(string $path): string
    {
        return base_path('tests/Fixtures/Workshops/'.$path);
    }

    protected function rarPayload(string $name, array $overrides = []): array
    {
        $payload = json_decode((string) file_get_contents($this->fixture("rar/{$name}.json")), true);

        return array_replace_recursive($payload, $overrides);
    }

    /** Stores and normalises one RAR record as the importer would. */
    protected function ingestRar(string $section, array $payload): WorkshopSourceRecord
    {
        $result = app(SourceRecordStore::class)->store(
            WorkshopDataSource::forKey(DataSourceCatalog::rarKey($section)),
            new SourceRecordData(
                recordType: 'authorization',
                externalId: RarRecordKeys::externalId($section, $payload),
                payload: $payload,
                identityKey: RarRecordKeys::identityKey($section, $payload),
                countyCode: RomanianCounties::resolve(data_get($payload, 'branch.address.county')),
            ),
        );

        $this->assertTrue(app(RecordNormalizers::class)->normalizeSafely($result->record), (string) $result->record->fresh()->parse_error);

        return $result->record->fresh();
    }

    protected function workshopFor(WorkshopSourceRecord $record): Workshop
    {
        return $record->link()->firstOrFail()->workshop;
    }

    /** @var array<string, list<array>|int> */
    private array $registryCounties = [];

    private string $registrySection = 'SERVICE';

    private bool $registryFaked = false;

    /**
     * The public registry, faked: county name => authorisation rows, paged by from/to exactly as
     * the portal does. A county mapped to an int answers with that HTTP status instead.
     *
     * Calling it again replaces what the registry lists. Http::fake keeps the first matching stub
     * for good, so the stub is registered once and reads the current contents on every request.
     *
     * @param  array<string, list<array>|int>  $counties
     */
    protected function fakeRegistry(array $counties, string $section = 'SERVICE'): void
    {
        $this->registryCounties = $counties;
        $this->registrySection = $section;

        if ($this->registryFaked) {
            return;
        }

        $this->registryFaked = true;

        Http::fake([
            '*/public/address/regions/RO' => fn () => Http::response(array_keys($this->registryCounties)),
            '*/public/RarPublicAuthorizations/*' => function (Request $request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                if (! str_contains($request->url(), '/RarPublicAuthorizations/'.$this->registrySection.'?')) {
                    return Http::response([]);
                }

                $rows = $this->registryCounties[$query['county'] ?? ''] ?? [];

                if (is_int($rows)) {
                    return Http::response(['error' => 'unavailable'], $rows);
                }

                return Http::response(array_slice($rows, (int) $query['from'], (int) $query['to'] - (int) $query['from']));
            },
        ]);
    }
}
