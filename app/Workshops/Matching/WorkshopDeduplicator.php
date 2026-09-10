<?php

namespace App\Workshops\Matching;

use App\Enums\WorkshopMatchStatus;
use App\Models\Workshop;
use App\Models\WorkshopMatchCandidate;
use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\TokenSimilarity;
use Illuminate\Support\Collection;

/**
 * Finds workshops that are the same place, county by county.
 *
 * Only workshops that share something concrete are compared (a phone, a website, a distinctive
 * name word in the same locality, an address, a 200 m square), so the work grows with the data,
 * not with its square. A pair that is certain and allowed to merge is merged; a likely one waits
 * in the review queue; a pair a person already decided is never reopened.
 */
class WorkshopDeduplicator
{
    public const AUTO_MERGE_SCORE = 85;

    public const REVIEW_SCORE = 55;

    /** A block this large is a chain's call centre or a generic word, not a duplicate. */
    private const MAX_BLOCK = 40;

    public function __construct(private WorkshopPairScorer $scorer, private WorkshopMerger $merger) {}

    /** @return array{compared: int, auto_merged: int, candidates: int} */
    public function run(?string $countyCode = null, bool $dryRun = false, ?callable $progress = null): array
    {
        $counts = ['compared' => 0, 'auto_merged' => 0, 'candidates' => 0];
        $counties = $countyCode !== null
            ? [$countyCode]
            : Workshop::query()->canonical()->whereNotNull('county_code')->distinct()->orderBy('county_code')->pluck('county_code')->all();

        foreach ($counties as $county) {
            $workshops = Workshop::query()
                ->canonical()
                ->where('county_code', $county)
                ->with(['contacts:id,workshop_id,type,normalized_value', 'authorizations:id,workshop_id,is_current,system'])
                ->get()
                ->keyBy('id');

            $decided = WorkshopMatchCandidate::query()
                ->whereIn('workshop_a_id', $workshops->keys()->all())
                ->where('status', '!=', WorkshopMatchStatus::Pending)
                ->get(['workshop_a_id', 'workshop_b_id'])
                ->mapWithKeys(fn (WorkshopMatchCandidate $pair): array => [$pair->workshop_a_id.'-'.$pair->workshop_b_id => true])
                ->all();

            foreach ($this->pairs($workshops) as [$aId, $bId]) {
                $a = $workshops->get($aId);
                $b = $workshops->get($bId);

                if ($a === null || $b === null || $a->merged_into_id !== null || $b->merged_into_id !== null || isset($decided["{$aId}-{$bId}"])) {
                    continue;
                }

                $result = $this->scorer->score($a, $b);
                $counts['compared']++;

                if ($result['score'] >= self::AUTO_MERGE_SCORE && $result['auto']) {
                    $counts['auto_merged']++;

                    if (! $dryRun) {
                        [$survivor, $duplicate] = WorkshopMerger::order($a, $b);
                        $this->merger->merge($survivor, $duplicate, $result['evidence'], score: $result['score']);
                        $duplicate->merged_into_id = $survivor->id;
                    }
                } elseif ($result['score'] >= self::REVIEW_SCORE) {
                    $counts['candidates']++;

                    if (! $dryRun) {
                        WorkshopMatchCandidate::query()->updateOrCreate(
                            ['workshop_a_id' => $aId, 'workshop_b_id' => $bId],
                            ['score' => $result['score'], 'evidence' => $result['evidence'], 'status' => WorkshopMatchStatus::Pending],
                        );
                    }
                }
            }

            $progress && $progress("{$county}: {$workshops->count()} workshops");
        }

        return $counts;
    }

    /** @return list<array{0: int, 1: int}> pairs, smaller id first */
    private function pairs(Collection $workshops): array
    {
        $blocks = [];

        foreach ($workshops as $workshop) {
            foreach ($workshop->contacts->whereIn('type', ['phone', 'mobile', 'website']) as $contact) {
                $blocks['contact:'.$contact->type.':'.$contact->normalized_value][] = $workshop->id;
            }

            $words = array_filter(TokenSimilarity::tokens((string) $workshop->normalized_name), fn (string $word): bool => strlen($word) >= 4 && ! in_array($word, ['auto', 'service', 'servis', 'impex', 'comexim', 'prod', 'trans', 'grup', 'group'], true));
            usort($words, fn (string $x, string $y): int => strlen($y) <=> strlen($x));

            if ($workshop->normalized_locality !== null && $words !== []) {
                $blocks['name:'.$workshop->normalized_locality.':'.$words[0]][] = $workshop->id;
            }

            $fingerprint = AddressNormalizer::fingerprint($workshop->address, $workshop->county_code);

            if ($fingerprint !== '') {
                $blocks['address:'.$fingerprint][] = $workshop->id;
            }

            if ($workshop->hasCoordinates() && ($workshop->coordinates_confidence ?? 0) >= 45) {
                $blocks['cell:'.round($workshop->latitude / 0.002).':'.round($workshop->longitude / 0.003)][] = $workshop->id;
            }
        }

        $pairs = [];

        foreach ($blocks as $ids) {
            $ids = array_values(array_unique($ids));

            if (count($ids) < 2 || count($ids) > self::MAX_BLOCK) {
                continue;
            }

            sort($ids);

            for ($i = 0, $n = count($ids); $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $pairs["{$ids[$i]}-{$ids[$j]}"] = [$ids[$i], $ids[$j]];
                }
            }
        }

        return array_values($pairs);
    }
}
