<?php

namespace App\Catalog\Enrichment\Wikidata;

use Illuminate\Support\Str;

class WikidataCandidateMatcher
{
    /** @param array<int, array<string, mixed>> $results */
    public function match(array $results, string $entityType, string $targetName, ?string $makeName = null): array
    {
        $scored = collect($results)
            ->filter(fn ($row) => is_array($row) && preg_match('/^Q\d+$/', (string) ($row['id'] ?? '')))
            ->map(function (array $row) use ($entityType, $targetName, $makeName): array {
                $row['_score'] = $this->score($row, $entityType, $targetName, $makeName);

                return $row;
            })
            ->sortByDesc('_score')
            ->values();

        $top = $scored->get(0);
        if (! $top) {
            return ['status' => 'skipped', 'confidence' => 0, 'candidate' => null, 'message' => 'No Wikidata candidate found.'];
        }

        $threshold = match ($entityType) {
            'vehicle_generation' => 90,
            default => 70,
        };
        $score = (float) $top['_score'];
        if ($score < $threshold) {
            return ['status' => 'skipped', 'confidence' => $score, 'candidate' => $top, 'message' => 'Best Wikidata candidate is below the confidence threshold.'];
        }

        $second = $scored->get(1);
        if ($second && $score - (float) $second['_score'] < 8) {
            return ['status' => 'ambiguous', 'confidence' => $score, 'candidate' => $top, 'message' => 'Two Wikidata candidates score too closely for automatic publication.'];
        }

        return ['status' => 'matched', 'confidence' => min(100, $score), 'candidate' => $top, 'message' => null];
    }

    private function score(array $row, string $entityType, string $targetName, ?string $makeName): float
    {
        $target = $this->normalize($targetName);
        $label = $this->normalize((string) ($row['label'] ?? ''));
        $matchText = $this->normalize((string) data_get($row, 'match.text', ''));
        $description = $this->normalize((string) ($row['description'] ?? ''));
        $make = $this->normalize((string) $makeName);
        $score = 0;

        if ($target !== '' && $label === $target) {
            $score += 55;
        } elseif ($target !== '' && ($label !== '' && (str_contains($label, $target) || str_contains($target, $label)))) {
            $score += 25;
        }

        if ($target !== '' && $matchText === $target) {
            $score += 15;
        }

        $contextTerms = $entityType === 'vehicle_make'
            ? ['automobile manufacturer', 'automaker', 'car manufacturer', 'motor vehicle manufacturer']
            : ['automobile', 'car model', 'vehicle model', 'motor vehicle', 'sport utility vehicle', 'suv'];
        if (collect($contextTerms)->contains(fn ($term) => str_contains($description, $term))) {
            $score += 20;
        }

        if ($entityType !== 'vehicle_make' && $make !== '' && str_contains($description, $make)) {
            $score += 15;
        }

        if (preg_match('/^Q\d+$/', (string) ($row['id'] ?? ''))) {
            $score += 5;
        }

        return min(100, $score);
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }
}
