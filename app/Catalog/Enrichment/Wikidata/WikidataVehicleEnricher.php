<?php

namespace App\Catalog\Enrichment\Wikidata;

use App\Catalog\Canonicalization\SourceAssertionWriter;
use App\Models\CatalogImportRun;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use App\Models\VehicleAlias;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class WikidataVehicleEnricher
{
    public function __construct(
        private readonly WikidataClient $client,
        private readonly WikidataCandidateMatcher $matcher,
        private readonly SourceAssertionWriter $assertions,
    ) {}

    /** @return array{status:string, confidence:float, qid:?string, message:?string} */
    public function enrich(CatalogSource $source, CatalogImportRun $run, string $entityType, Model $entity): array
    {
        [$targetName, $makeName] = $this->targetContext($entityType, $entity);
        $searchLanguage = (string) ($source->settings['search_language'] ?? 'en');
        $results = $this->client->search($source, $targetName, $searchLanguage);
        $match = $this->matcher->match($results, $entityType, $targetName, $makeName);
        $candidate = $match['candidate'];
        $qid = is_array($candidate) ? (string) ($candidate['id'] ?? '') : '';
        $details = [];

        if ($match['status'] === 'matched' && $qid !== '') {
            $details = $this->client->entity($source, $qid, $this->languages($source));
        }

        $payload = [
            'target' => ['entity_type' => $entityType, 'entity_id' => $entity->getKey(), 'name' => $targetName, 'make' => $makeName],
            'search_results' => $results,
            'selected_qid' => $qid ?: null,
            'entity' => $details,
        ];
        $checksum = hash('sha256', json_encode(Arr::sortRecursive($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $record = CatalogSourceRecord::query()->updateOrCreate(
            [
                'catalog_source_id' => $source->id,
                'record_type' => 'wikidata_vehicle_enrichment',
                'external_id' => $entityType.':'.$entity->getKey(),
            ],
            [
                'catalog_import_run_id' => $run->id,
                'checksum_sha256' => $checksum,
                'raw_payload' => $payload,
                'normalized_payload' => $details ?: null,
                'mapping_status' => $match['status'] === 'matched' ? 'published' : $match['status'],
                'mapping_notes' => $match['message'],
                'canonical_entity_type' => $entityType,
                'canonical_entity_id' => $entity->getKey(),
                'mapping_confidence' => $match['confidence'],
                'deleted_at_source' => false,
                'last_seen_at' => now(),
            ],
        );

        if ($match['status'] !== 'matched' || $qid === '') {
            return ['status' => $match['status'], 'confidence' => (float) $match['confidence'], 'qid' => $qid ?: null, 'message' => $match['message']];
        }

        $this->publishAliases($source, $entityType, (int) $entity->getKey(), $targetName, $details);
        $preferredLabel = $this->preferredText($details['labels'] ?? [], $this->languages($source));
        $preferredDescription = $this->preferredText($details['descriptions'] ?? [], $this->languages($source));
        $this->assertions->write($record, $entityType, (int) $entity->getKey(), [
            'wikidata_qid' => $qid,
            'wikidata_label' => $preferredLabel,
            'wikidata_description' => $preferredDescription,
        ], (float) $match['confidence']);

        return ['status' => 'published', 'confidence' => (float) $match['confidence'], 'qid' => $qid, 'message' => null];
    }

    /** @return array{0:string,1:?string} */
    private function targetContext(string $entityType, Model $entity): array
    {
        return match ($entityType) {
            'vehicle_make' => [$this->requiredName($entity), null],
            'vehicle_model' => [$this->requiredName($entity), $entity instanceof VehicleModel ? $entity->make?->name : null],
            'vehicle_generation' => [
                $entity instanceof VehicleGeneration ? trim(($entity->model?->name ?? '').' '.$entity->name) : $this->requiredName($entity),
                $entity instanceof VehicleGeneration ? $entity->model?->make?->name : null,
            ],
            default => throw new RuntimeException("Unsupported Wikidata vehicle entity type: {$entityType}."),
        };
    }

    private function requiredName(Model $entity): string
    {
        $name = trim((string) $entity->getAttribute('name'));
        throw_if($name === '', RuntimeException::class, 'Vehicle entity has no name to enrich.');

        return $name;
    }

    private function publishAliases(CatalogSource $source, string $entityType, int $entityId, string $canonicalName, array $details): void
    {
        foreach ($this->localizedValues($details) as [$language, $alias]) {
            $normalized = $this->normalizeAlias($alias);
            if ($normalized === '' || $normalized === $this->normalizeAlias($canonicalName)) {
                continue;
            }

            VehicleAlias::query()->updateOrCreate(
                [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'normalized_alias' => $normalized,
                    'language_code' => $language,
                    'catalog_source_id' => $source->id,
                ],
                ['alias' => $alias],
            );
        }
    }

    /** @return iterable<array{0:string,1:string}> */
    private function localizedValues(array $details): iterable
    {
        foreach ((array) ($details['labels'] ?? []) as $language => $value) {
            $text = trim((string) data_get($value, 'value', ''));
            if ($text !== '') {
                yield [(string) $language, $text];
            }
        }

        foreach ((array) ($details['aliases'] ?? []) as $language => $values) {
            foreach ((array) $values as $value) {
                $text = trim((string) data_get($value, 'value', ''));
                if ($text !== '') {
                    yield [(string) $language, $text];
                }
            }
        }
    }

    private function preferredText(array $values, array $languages): ?string
    {
        foreach ($languages as $language) {
            $text = trim((string) data_get($values, $language.'.value', ''));
            if ($text !== '') {
                return $text;
            }
        }

        $first = reset($values);
        $text = trim((string) data_get($first, 'value', ''));

        return $text !== '' ? $text : null;
    }

    /** @return array<int, string> */
    private function languages(CatalogSource $source): array
    {
        $languages = $source->settings['languages'] ?? ['en', 'ro', 'de', 'fr', 'it', 'es'];

        return array_values(array_filter(array_map('strval', is_array($languages) ? $languages : explode(',', (string) $languages))));
    }

    private function normalizeAlias(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish();
    }
}
