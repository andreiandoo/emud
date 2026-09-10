<?php

namespace App\Workshops\Sources\Rar;

use App\Workshops\Contracts\WorkshopSource;
use App\Workshops\Data\SourcePartition;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Support\RomanianCounties;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One section of the RAR registry (SERVICE, ITP, GPL, TLV or B4), county by county.
 */
class RarRegistrySource implements WorkshopSource
{
    /** A registry that ignored from/to would otherwise page forever. */
    private const MAX_PAGES_PER_COUNTY = 200;

    public function __construct(private RarClient $client, private string $section) {}

    public function key(): string
    {
        return DataSourceCatalog::rarKey($this->section);
    }

    public function section(): string
    {
        return $this->section;
    }

    /**
     * The counties as the registry lists them today. The static county table is used only when
     * the registry cannot answer, and then with the registry's own ASCII spellings.
     */
    public function discover(): array
    {
        try {
            $names = $this->client->counties();
        } catch (Throwable $exception) {
            Log::warning('RAR county list unavailable; using the reference list.', ['error' => $exception->getMessage()]);
            $names = [];
        }

        if ($names === []) {
            $names = array_map(fn (array $county): string => str_replace(['ș', 'ț', 'ă', 'â', 'î', 'Ș', 'Ț', 'Ă', 'Â', 'Î'], ['s', 't', 'a', 'a', 'i', 'S', 'T', 'A', 'A', 'I'], $county[0]), RomanianCounties::ALL);
        }

        return array_map(
            fn (string $name): SourcePartition => new SourcePartition($name, $name, RomanianCounties::resolve($name)),
            array_values(array_unique($names)),
        );
    }

    public function fetch(SourcePartition $partition): iterable
    {
        $size = max(1, (int) config('workshops.rar.page_size'));

        for ($page = 0, $from = 0; $page < self::MAX_PAGES_PER_COUNTY; $page++, $from += $size) {
            $rows = $this->client->authorizations($this->section, $partition->label, $from, $from + $size);

            foreach ($rows as $row) {
                yield new SourceRecordData(
                    recordType: 'authorization',
                    externalId: RarRecordKeys::externalId($this->section, $row),
                    payload: $row,
                    identityKey: RarRecordKeys::identityKey($this->section, $row),
                    countyCode: $partition->countyCode ?? RomanianCounties::resolve(data_get($row, 'branch.address.county')),
                    sourceReference: $this->client->authorizationsUrl($this->section, $partition->label, $from, $from + $size),
                    httpStatus: 200,
                );
            }

            if (count($rows) < $size) {
                return;
            }
        }

        throw new RarResponseException("{$this->section} / {$partition->label} did not stop after ".self::MAX_PAGES_PER_COUNTY.' pages.');
    }
}
