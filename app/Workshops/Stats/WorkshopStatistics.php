<?php

namespace App\Workshops\Stats;

use App\Enums\WorkshopMatchStatus;
use App\Enums\WorkshopRecordMatchStatus;
use App\Models\Workshop;
use App\Models\WorkshopAuthorizationActivity;
use App\Models\WorkshopCapability;
use App\Models\WorkshopCompany;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopImportRun;
use App\Models\WorkshopMatchCandidate;
use App\Models\WorkshopRecordMatch;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Support\RomanianCounties;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How much of the country the registry covers, and how well. Shared by workshops:status, the RAR
 * statistics command and the admin overview, so all three always report the same numbers.
 */
class WorkshopStatistics
{
    /** @return array<string, int> */
    public function overview(): array
    {
        $rarSources = WorkshopDataSource::query()->whereIn('key', DataSourceCatalog::rarKeys())->pluck('id');
        $osmSource = WorkshopDataSource::query()->where('key', DataSourceCatalog::OSM)->value('id');
        $records = WorkshopSourceRecord::query();

        return [
            'source_records' => (clone $records)->count(),
            'rar_records' => (clone $records)->whereIn('data_source_id', $rarSources)->where('record_type', 'authorization')->count(),
            'rar_current_records' => (clone $records)->whereIn('data_source_id', $rarSources)->where('record_type', 'authorization')->where('is_current', true)->count(),
            'workshops' => $this->workshops()->count(),
            'active_workshops' => $this->workshops()->where('is_active', true)->count(),
            'companies' => WorkshopCompany::query()->count(),
            'multi_location_companies' => WorkshopCompany::query()->whereHas('workshops', fn (Builder $query) => $query->canonical(), '>', 1)->count(),
            'rar_authorized' => $this->workshops()->where('is_rar_authorized', true)->count(),
            'itp' => $this->workshops()->where('is_itp', true)->count(),
            'gpl_gnc' => $this->workshops()->where('is_gpl_gnc', true)->count(),
            'tlv' => $this->workshops()->where('is_tlv', true)->count(),
            'modifications' => $this->workshops()->where('is_modification_authorized', true)->count(),
            'with_phone' => $this->withContact(['phone', 'mobile']),
            'with_email' => $this->withContact(['email']),
            'with_website' => $this->withContact(['website']),
            'with_coordinates' => $this->workshops()->whereNotNull('latitude')->count(),
            'with_precise_coordinates' => $this->workshops()->where('coordinates_confidence', '>=', 45)->count(),
            'with_point_of_work_address' => $this->workshops()->whereNotNull('address')->count(),
            'rar_matched_onrc' => $this->workshops()->where('is_rar_authorized', true)->whereHas('company', fn (Builder $query) => $query->whereNotNull('onrc_verified_at'))->count(),
            'rar_matched_osm' => $osmSource === null ? 0 : $this->workshops()->where('is_rar_authorized', true)->whereHas('sourceLinks', fn (Builder $query) => $query->where('data_source_id', $osmSource))->count(),
            'supports_4x4' => $this->workshops()->where('supports_4x4', true)->count(),
            'awd_permanent' => WorkshopCapability::query()->where('capability', 'awd_permanent')->where('value', true)->count(),
            'offroad_relevant' => $this->workshops()->where('offroad_score', '>=', 40)->count(),
            'offroad_specialists' => WorkshopCapability::query()->where('capability', 'offroad')->where('value', true)->count(),
            'supports_ev' => $this->workshops()->where('supports_ev', true)->count(),
            'supports_hybrid' => $this->workshops()->where('supports_hybrid', true)->count(),
            'supports_trucks' => $this->workshops()->where('supports_trucks', true)->count(),
            'itp_4x4' => WorkshopCapability::query()->where('capability', 'itp_4x4')->where('value', true)->count(),
            'pending_website' => $this->workshops()->where('is_active', true)->where('website_status', 'pending')->count(),
            'pending_geocode' => $this->workshops()->where('geocode_status', 'pending')->count(),
            // What the review queue shows: ONRC and OSM matches nobody has decided, and pending pairs.
            'matches_to_review' => WorkshopRecordMatch::query()->whereNull('reviewed_by')->whereIn('status', [WorkshopRecordMatchStatus::Ambiguous, WorkshopRecordMatchStatus::Probable])->count()
                + WorkshopMatchCandidate::query()->where('status', WorkshopMatchStatus::Pending)->count(),
            'failed_records' => (clone $records)->where('parse_status', WorkshopSourceRecord::STATUS_FAILED)->count(),
            'pending_records' => (clone $records)->where('parse_status', WorkshopSourceRecord::STATUS_PENDING)->whereIn('record_type', ['authorization', 'company', 'poi'])->count(),
            'unique_rar_activities' => WorkshopAuthorizationActivity::query()
                ->whereHas('authorization', fn (Builder $query) => $query->where('is_current', true))
                ->distinct()
                ->count('code'),
        ];
    }

    /** @return Collection<int, array{code: string, county: string, workshops: int, rar: int, itp: int, with_phone: int}> */
    public function byCounty(): Collection
    {
        $rows = $this->workshops()
            ->select('county_code')
            ->selectRaw('count(*) as workshops')
            ->selectRaw('sum(case when is_rar_authorized then 1 else 0 end) as rar')
            ->selectRaw('sum(case when is_itp then 1 else 0 end) as itp')
            ->groupBy('county_code')
            ->get()
            ->keyBy('county_code');

        $phones = $this->workshops()
            ->whereExists(fn ($query) => $query->select(DB::raw(1))->from('workshop_contacts')->whereColumn('workshop_contacts.workshop_id', 'workshops.id')->whereIn('type', ['phone', 'mobile']))
            ->select('county_code')
            ->selectRaw('count(*) as total')
            ->groupBy('county_code')
            ->pluck('total', 'county_code');

        return collect(RomanianCounties::ALL)->map(fn (array $county, string $code): array => [
            'code' => $code,
            'county' => $county[0],
            'workshops' => (int) ($rows[$code]->workshops ?? 0),
            'rar' => (int) ($rows[$code]->rar ?? 0),
            'itp' => (int) ($rows[$code]->itp ?? 0),
            'with_phone' => (int) ($phones[$code] ?? 0),
        ])->values();
    }

    /** @return Collection<int, array{section: string, key: string, records: int, current: int, parsed: int, failed: int, pending: int}> */
    public function bySection(): Collection
    {
        return collect(DataSourceCatalog::rarSections())->map(function (string $section): array {
            $key = DataSourceCatalog::rarKey($section);
            $source = WorkshopDataSource::query()->where('key', $key)->first();
            $records = WorkshopSourceRecord::query()->where('data_source_id', $source?->id ?? 0)->where('record_type', 'authorization');

            return [
                'section' => $section,
                'key' => $key,
                'records' => (clone $records)->count(),
                'current' => (clone $records)->where('is_current', true)->count(),
                'parsed' => (clone $records)->where('parse_status', WorkshopSourceRecord::STATUS_PARSED)->count(),
                'failed' => (clone $records)->where('parse_status', WorkshopSourceRecord::STATUS_FAILED)->count(),
                'pending' => (clone $records)->where('parse_status', WorkshopSourceRecord::STATUS_PENDING)->count(),
            ];
        });
    }

    /** @return Collection<int, WorkshopImportRun> */
    public function latestRuns(int $limit = 10): Collection
    {
        return WorkshopImportRun::query()->with('dataSource:id,key,name')->latest('id')->limit($limit)->get();
    }

    private function workshops(): Builder
    {
        return Workshop::query()->canonical();
    }

    private function withContact(array $types): int
    {
        return $this->workshops()
            ->whereExists(fn ($query) => $query->select(DB::raw(1))->from('workshop_contacts')->whereColumn('workshop_contacts.workshop_id', 'workshops.id')->whereIn('type', $types))
            ->count();
    }
}
