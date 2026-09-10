<?php

namespace App\Console\Commands;

use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Sources\Rar\RarAuthorizationParser;
use App\Workshops\Sources\Rar\RarClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * The registry is someone else's application and can change without notice. This asks it one
 * small question and checks the answer still has the shape the importer relies on, storing
 * nothing. The test suite never touches the live registry; this is the opt-in check that does.
 */
class ProbeRarRegistry extends Command
{
    protected $signature = 'workshops:rar:probe {--section=SERVICE} {--county=Covasna : As the registry spells it}';

    protected $description = 'Check that the live RAR registry still answers the way the importer expects (one small request, nothing stored).';

    public function handle(RarClient $client, RarAuthorizationParser $parser): int
    {
        $section = strtoupper((string) $this->option('section'));

        if (! in_array($section, DataSourceCatalog::rarSections(), true)) {
            $this->error("Unknown section [{$section}].");

            return self::FAILURE;
        }

        try {
            $counties = $client->counties();
            $rows = $client->authorizations($section, (string) $this->option('county'), 0, 3);
        } catch (Throwable $exception) {
            $this->error('The registry did not answer: '.$exception->getMessage());

            return self::FAILURE;
        }

        $parsed = [];
        $parseError = null;

        foreach ($rows as $row) {
            try {
                $parsed[] = $parser->parse($section, $row);
            } catch (Throwable $exception) {
                $parseError = $exception->getMessage();
            }
        }

        $checks = [
            'the county list names 40 or more counties' => count($counties) >= 40,
            'the section answers with authorisations' => $rows !== [],
            'every authorisation has an exit number' => $rows !== [] && collect($rows)->every(fn (array $row): bool => filled($row['exitNo'] ?? null)),
            'every authorisation names its organisation' => $rows !== [] && collect($rows)->every(fn (array $row): bool => filled(data_get($row, 'branch.organisationInfo.name'))),
            'every authorisation has a point of work' => $rows !== [] && collect($rows)->every(fn (array $row): bool => filled(data_get($row, 'branch.address.originalAddress')) || filled(data_get($row, 'branch.address.street'))),
            'every authorisation can be read' => $rows !== [] && $parseError === null,
            'activities come with their codes' => $parsed !== [] && collect($parsed)->every(fn ($data): bool => $data->activities !== []),
        ];

        $this->table(['Check', 'Result'], collect($checks)->map(fn (bool $passed, string $check): array => [$check, $passed ? 'ok' : 'FAILED'])->values()->all());

        if ($parseError !== null) {
            $this->warn('Reading failed: '.$parseError);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
