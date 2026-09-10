<?php

namespace App\Console\Commands;

use App\Workshops\Stats\WorkshopStatistics;
use Illuminate\Console\Command;

class ReportWorkshopStatus extends Command
{
    protected $signature = 'workshops:status';

    protected $description = 'Coverage of the national workshop registry across every source, and the latest import runs.';

    public function handle(WorkshopStatistics $statistics): int
    {
        $o = $statistics->overview();

        $this->table(['Measure', 'Count'], [
            ['Source records (all sources)', $o['source_records']],
            ['RAR records (current)', "{$o['rar_records']} ({$o['rar_current_records']})"],
            ['Normalized workshops', $o['workshops']],
            ['… active', $o['active_workshops']],
            ['Companies', $o['companies']],
            ['Workshops with phone', $o['with_phone']],
            ['Workshops with email', $o['with_email']],
            ['Workshops with website', $o['with_website']],
            ['Workshops with coordinates', $o['with_coordinates']],
            ['… street-level coordinates', $o['with_precise_coordinates']],
            ['RAR matched to ONRC', $o['rar_matched_onrc']],
            ['RAR matched to OSM', $o['rar_matched_osm']],
            ['RAR service / ITP / GPL-GNC / TLV / B4', "{$o['rar_authorized']} / {$o['itp']} / {$o['gpl_gnc']} / {$o['tlv']} / {$o['modifications']}"],
            ['4x4 capable', $o['supports_4x4']],
            ['Off-road specialists (score ≥ 60 with specialist evidence)', $o['offroad_specialists']],
            ['EV capable', $o['supports_ev']],
            ['Hybrid capable', $o['supports_hybrid']],
            ['Truck capable', $o['supports_trucks']],
            ['ITP stations able to inspect permanent 4x4', $o['itp_4x4']],
            ['Pending website enrichment', $o['pending_website']],
            ['Pending geocoding', $o['pending_geocode']],
            ['Ambiguous matches to review', $o['ambiguous_matches']],
            ['Failed source records', $o['failed_records']],
            ['Pending source records', $o['pending_records']],
        ]);

        $runs = $statistics->latestRuns();

        if ($runs->isNotEmpty()) {
            $this->table(
                ['Run', 'Source', 'Status', 'Started', 'Seen', 'New', 'Changed', 'Unchanged', 'Failed', 'Retired'],
                $runs->map(fn ($run): array => [
                    substr($run->uuid, 0, 8),
                    $run->dataSource?->key,
                    $run->status->value,
                    $run->started_at?->timezone('Europe/Bucharest')->format('d.m H:i'),
                    $run->discovered_count,
                    $run->created_count,
                    $run->updated_count,
                    $run->unchanged_count,
                    $run->failed_count,
                    $run->retired_count,
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
