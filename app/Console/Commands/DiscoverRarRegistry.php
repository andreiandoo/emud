<?php

namespace App\Console\Commands;

use App\Models\WorkshopDataSource;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\SourceRecordStore;
use App\Workshops\Sources\Rar\RarClient;
use App\Workshops\Sources\Rar\RarNomenclature;
use App\Workshops\Support\RomanianCounties;
use Illuminate\Console\Command;
use Throwable;

class DiscoverRarRegistry extends Command
{
    protected $signature = 'workshops:rar:discover';

    protected $description = 'Refresh the RAR county list and activity nomenclature from the public registry.';

    public function handle(RarClient $client, RarNomenclature $nomenclature, SourceRecordStore $store): int
    {
        try {
            $document = $client->nomenclature();
            $record = $nomenclature->store($document, $store);
            $sections = collect($document['RarAuthorizationActivitiesEnumBySection'] ?? [])->map(fn ($codes) => count((array) $codes));
            $this->info('Activity nomenclature stored (record #'.$record->id.'): '.$sections->map(fn ($count, $section) => "{$section} {$count}")->implode(', '));
        } catch (Throwable $exception) {
            $this->warn('Nomenclature unavailable, the bundled copy stays in use: '.$exception->getMessage());
        }

        try {
            $counties = $client->counties();
        } catch (Throwable $exception) {
            $this->error('The registry did not return its county list: '.$exception->getMessage());

            return self::FAILURE;
        }

        $rows = array_map(fn (string $name): array => [$name, RomanianCounties::resolve($name) ?? '— necunoscut'], $counties);
        $this->table(['Registry spelling', 'County code'], $rows);

        $unmapped = array_filter($rows, fn (array $row): bool => str_starts_with($row[1], '—'));

        if ($unmapped !== []) {
            $this->warn(count($unmapped).' county names do not map to a code; they are still imported, without a county code.');
        }

        $payload = array_map(fn (array $row): array => ['name' => $row[0], 'code' => RomanianCounties::resolve($row[0])], $rows);

        foreach (DataSourceCatalog::rarSections() as $section) {
            $source = WorkshopDataSource::forKey(DataSourceCatalog::rarKey($section));
            $source->putState('counties', array_map(fn (array $county): array => ['key' => $county['name'], 'label' => $county['name'], 'county_code' => $county['code'], 'parameters' => []], $payload));
            $source->putState('counties_discovered_at', now()->toIso8601String());
        }

        $store->store(
            WorkshopDataSource::forKey(DataSourceCatalog::rarKey('SERVICE')),
            new SourceRecordData('county_list', 'RO', $payload, sourceReference: rtrim((string) config('workshops.rar.base_url'), '/').'/public/address/regions/RO', httpStatus: 200),
        )->record->markParsed();

        $this->info(count($counties).' counties discovered.');

        return self::SUCCESS;
    }
}
