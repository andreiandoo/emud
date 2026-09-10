<?php

namespace App\Workshops\Sources\Rar;

use App\Workshops\Support\Identifiers;
use App\Workshops\Support\TextNormalizer;

/**
 * How RAR records are identified. The registry exposes no record id, so this is derived from
 * what was observed in the data (2026-09-10, all five sections):
 *
 *  - The exit number (exitNo, "OCS.BV.NI.244886") identifies one authorisation document. Every
 *    revision gets a new one, and for a while RAR lists the old and new revision side by side.
 *  - The audit file (auditFileNo, "BV0351") stays the same across revisions of a workshop's
 *    authorisation, but in the ITP section two companies can share one, so it is paired with the
 *    fiscal code. An ITP station has its own stable code (stationCode, "AB080"), used first.
 */
class RarRecordKeys
{
    public static function externalId(string $system, array $row): string
    {
        $exit = TextNormalizer::clean($row['exitNo'] ?? null);

        if ($exit !== null) {
            return strtoupper($system).':'.$exit;
        }

        $parts = array_filter([
            TextNormalizer::clean($row['no'] ?? null),
            TextNormalizer::clean($row['auditFileNo'] ?? null),
            TextNormalizer::clean($row['validFrom'] ?? null),
            Identifiers::cui(data_get($row, 'branch.organisationInfo.taxRegisterNo')),
        ]);

        return strtoupper($system).':'.($parts === [] ? sha1(json_encode($row) ?: '') : implode('|', $parts));
    }

    public static function identityKey(string $system, array $row): ?string
    {
        $system = strtoupper($system);

        if ($system === 'ITP' && ($station = TextNormalizer::clean($row['stationCode'] ?? null)) !== null) {
            return 'ITP:'.$station;
        }

        $audit = TextNormalizer::clean($row['auditFileNo'] ?? null);

        if ($audit === null) {
            return null;
        }

        return $system.':'.$audit.':'.(Identifiers::cui(data_get($row, 'branch.organisationInfo.taxRegisterNo')) ?? '-');
    }
}
