<?php

namespace App\Workshops\Sources\Onrc;

use App\Models\WorkshopCompany;
use App\Models\WorkshopImportRun;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Data\StoreResult;
use App\Workshops\Ingestion\RecordNormalizers;
use App\Workshops\Ingestion\SourceRecordStore;
use App\Workshops\Support\Identifiers;
use App\Workshops\Support\RomanianCounties;
use Throwable;

/**
 * Keeps from the national trade register only what the workshop registry needs:
 *  - every company authorised for vehicle repair under either CAEN revision (4520 in Rev. 2,
 *    9531 in Rev. 3), whatever its main activity, if it is operating; and
 *  - every company already known from RAR, operating or not, because its status matters.
 *
 * Four streaming passes over the files, none loaded whole: CAEN (which companies are
 * automotive), companies (which rows to keep), CAEN again (all activities of the kept ones),
 * statuses. Every kept company is stored as a source record and matched.
 */
class OnrcImporter
{
    public function __construct(private SourceRecordStore $store, private RecordNormalizers $normalizers) {}

    /**
     * @param  array<string, array{path: string}>  $files
     * @return array{automotive: int, kept: int, created: int, updated: int, unchanged: int, failed: int, retired: int}
     */
    public function import(OnrcRelease $release, array $files, WorkshopImportRun $run, ?callable $progress = null, ?int $limit = null): array
    {
        $startedAt = now()->startOfSecond();
        $targets = collect((array) config('workshops.onrc.caen_codes'))->mapWithKeys(fn (array $code): array => [$code['code'].'/'.$code['version'] => true])->all();
        $caenLabels = isset($files['N_CAEN.CSV']) ? $this->caenLabels($files['N_CAEN.CSV']['path']) : [];
        $statusLabels = isset($files['N_STARE_FIRMA.CSV']) ? $this->statusLabels($files['N_STARE_FIRMA.CSV']['path']) : [];

        $progress && $progress('reading authorised CAEN activities');
        $automotive = [];

        foreach (CaretSeparatedReader::rows($files['OD_CAEN_AUTORIZAT.CSV']['path']) as $row) {
            if (isset($targets[($row['COD_CAEN_AUTORIZAT'] ?? '').'/'.($row['VER_CAEN_AUTORIZAT'] ?? '')])) {
                $automotive[$row['COD_INMATRICULARE'] ?? ''] = true;
            }
        }

        unset($automotive['']);
        $progress && $progress(number_format(count($automotive)).' companies authorised for vehicle repair');

        $known = WorkshopCompany::query()->whereNotNull('cui')->pluck('cui')->flip()->all();
        $kept = [];

        $progress && $progress('reading companies');

        foreach (CaretSeparatedReader::rows($files['OD_FIRME.CSV']['path']) as $row) {
            $registration = $row['COD_INMATRICULARE'] ?? '';
            $cui = Identifiers::cui($row['CUI'] ?? null);

            if ($registration !== '' && (isset($automotive[$registration]) || ($cui !== null && isset($known[$cui])))) {
                $kept[$registration] = $row;
            }
        }

        $progress && $progress(number_format(count($kept)).' companies kept; collecting their activities and statuses');

        $caen = [];

        foreach (CaretSeparatedReader::rows($files['OD_CAEN_AUTORIZAT.CSV']['path']) as $row) {
            $registration = $row['COD_INMATRICULARE'] ?? '';

            if (isset($kept[$registration])) {
                $key = ($row['COD_CAEN_AUTORIZAT'] ?? '').'/'.($row['VER_CAEN_AUTORIZAT'] ?? '');
                $caen[$registration][$key] = [
                    'COD' => $row['COD_CAEN_AUTORIZAT'] ?? '',
                    'VER' => $row['VER_CAEN_AUTORIZAT'] ?? '',
                    'DENUMIRE' => $caenLabels[$key] ?? null,
                ];
            }
        }

        $statuses = [];

        foreach (CaretSeparatedReader::rows($files['OD_STARE_FIRMA.CSV']['path']) as $row) {
            [$registration, $code] = $this->statusColumns($row);

            if ($registration !== null && isset($kept[$registration]) && $code !== null) {
                $statuses[$registration][$code] = ['COD' => $code, 'DENUMIRE' => $statusLabels[$code] ?? null];
            }
        }

        $counts = ['automotive' => count($automotive), 'kept' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0, 'retired' => 0];
        $source = $run->dataSource;

        foreach ($kept as $registration => $row) {
            $isAutomotive = isset($automotive[$registration]);
            $companyStatuses = array_values($statuses[$registration] ?? []);
            $operating = in_array(OnrcCompany::ACTIVE_STATUS, array_column($companyStatuses, 'COD'), true);
            $cui = Identifiers::cui($row['CUI'] ?? null);

            // A register of every repair firm ever founded is not a lead list. Firms no longer
            // operating are kept only when RAR knows them, because then their status matters.
            if (! $operating && ! ($cui !== null && isset($known[$cui]))) {
                continue;
            }

            if ($limit !== null && $counts['kept'] >= $limit) {
                break;
            }

            $counts['kept']++;
            $run->tally('discovered');

            try {
                $result = $this->store->store($source, new SourceRecordData(
                    recordType: 'company',
                    externalId: $registration,
                    payload: $row + [
                        'CAEN' => array_values($caen[$registration] ?? []),
                        'STARI' => $companyStatuses,
                        'AUTO' => $isAutomotive,
                        'RELEASE' => $release->key,
                    ],
                    identityKey: $cui === null ? null : 'CUI:'.$cui,
                    countyCode: RomanianCounties::resolve($row['ADR_JUDET'] ?? null),
                    sourceReference: $release->resource('OD_FIRME.CSV')['url'] ?? null,
                ), $run);

                $counts[$result->outcome === StoreResult::DUPLICATE ? 'unchanged' : $result->outcome]++;
                $run->tally('fetched');
                $run->tally($result->outcome === StoreResult::DUPLICATE ? 'skipped' : $result->outcome);

                if ($result->needsParsing() && ! $this->normalizers->normalizeSafely($result->record)) {
                    $counts['failed']++;
                    $run->tally('failed');
                }
            } catch (Throwable $exception) {
                report($exception);
                $counts['failed']++;
                $run->tally('failed');
            }

            if ($progress && $counts['kept'] % 5000 === 0) {
                $progress(number_format($counts['kept']).' companies stored');
            }
        }

        if ($limit === null) {
            $counts['retired'] = $this->store->retireUnseen($source, 'company', $startedAt)->count();
            $run->tally('retired', $counts['retired']);
        }

        return $counts;
    }

    /** OD_STARE_FIRMA's columns have changed names between releases; find them by meaning. */
    private function statusColumns(array $row): array
    {
        $registration = null;
        $code = null;

        foreach ($row as $column => $value) {
            if ($registration === null && str_contains($column, 'INMATRICULARE')) {
                $registration = $value;
            } elseif ($code === null && (str_contains($column, 'STARE') || $column === 'COD')) {
                $code = $value;
            }
        }

        return [$registration, $code === '' ? null : $code];
    }

    /** @return array<string, string> "4520/2" => label */
    private function caenLabels(string $path): array
    {
        $labels = [];

        foreach (CaretSeparatedReader::rows($path) as $row) {
            if (($row['CLASA'] ?? '') !== '') {
                $labels[$row['CLASA'].'/'.($row['VERSIUNE_CAEN'] ?? '')] = $row['DENUMIRE'] ?? '';
            }
        }

        return $labels;
    }

    /** @return array<string, string> status code => label */
    private function statusLabels(string $path): array
    {
        $labels = [];

        foreach (CaretSeparatedReader::rows($path) as $row) {
            if (($row['COD'] ?? '') !== '') {
                $labels[$row['COD']] = $row['DENUMIRE'] ?? '';
            }
        }

        return $labels;
    }
}
