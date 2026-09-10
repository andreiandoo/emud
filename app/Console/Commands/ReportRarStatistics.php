<?php

namespace App\Console\Commands;

use App\Models\WorkshopAuthorizationActivity;
use App\Workshops\Stats\WorkshopStatistics;
use Illuminate\Console\Command;

class ReportRarStatistics extends Command
{
    protected $signature = 'workshops:rar:stats';

    protected $description = 'What the RAR import has produced: records, workshops, activities, capabilities, failures.';

    public function handle(WorkshopStatistics $statistics): int
    {
        $overview = $statistics->overview();

        $this->table(
            ['Section', 'Source', 'Records', 'Current', 'Parsed', 'Pending', 'Failed'],
            $statistics->bySection()->map(fn (array $row): array => [$row['section'], $row['key'], $row['records'], $row['current'], $row['parsed'], $row['pending'], $row['failed']])->all(),
        );

        $this->table(['Measure', 'Count'], [
            ['RAR authorisation records', $overview['rar_records']],
            ['… still listed by RAR', $overview['rar_current_records']],
            ['Workshops (unique places)', $overview['workshops']],
            ['RAR-authorised service workshops', $overview['rar_authorized']],
            ['Companies', $overview['companies']],
            ['Companies with several workshops', $overview['multi_location_companies']],
            ['With a phone', $overview['with_phone']],
            ['With a point-of-work address', $overview['with_point_of_work_address']],
            ['Unique RAR activity codes in use', $overview['unique_rar_activities']],
            ['4x4 (multi-axle transmission authorised)', $overview['supports_4x4']],
            ['Permanent all-wheel drive authorised', $overview['awd_permanent']],
            ['Off-road relevant (score ≥ 40)', $overview['offroad_relevant']],
            ['EV capable', $overview['supports_ev']],
            ['Hybrid capable', $overview['supports_hybrid']],
            ['Truck / bus capable', $overview['supports_trucks']],
            ['Parse failures', $overview['failed_records']],
        ]);

        $this->table(
            ['County', 'Workshops', 'RAR service', 'ITP', 'With phone'],
            $statistics->byCounty()->map(fn (array $row): array => ["{$row['county']} ({$row['code']})", $row['workshops'], $row['rar'], $row['itp'], $row['with_phone']])->all(),
        );

        $top = WorkshopAuthorizationActivity::query()
            ->whereHas('authorization', fn ($query) => $query->where('is_current', true))
            ->selectRaw('display_code, count(*) as total')
            ->groupBy('display_code')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $this->table(['Most common activity', 'Authorisations'], $top->map(fn ($row): array => [$row->display_code, $row->total])->all());

        return self::SUCCESS;
    }
}
