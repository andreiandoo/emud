<?php

namespace Tests\Feature\Workshops;

use App\Enums\WorkshopMatchStatus;
use App\Models\Workshop;
use App\Models\WorkshopCompany;
use App\Models\WorkshopContact;
use App\Models\WorkshopMatchCandidate;
use App\Workshops\Matching\WorkshopDeduplicator;
use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\CompanyNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class DeduplicationTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
    }

    private function workshop(string $name, string $address, float $lat, float $lng, ?string $phone = null, array $attributes = []): Workshop
    {
        $workshop = Workshop::query()->create($attributes + [
            'name' => $name,
            'normalized_name' => CompanyNameNormalizer::normalize($name),
            'address' => $address,
            'normalized_address' => AddressNormalizer::normalize($address),
            'locality' => 'Brașov',
            'normalized_locality' => 'brasov',
            'county' => 'Brașov',
            'county_code' => 'BV',
            'latitude' => $lat,
            'longitude' => $lng,
            'coordinates_confidence' => 85,
            'is_active' => true,
        ]);

        if ($phone !== null) {
            WorkshopContact::query()->create(['workshop_id' => $workshop->id, 'type' => 'mobile', 'value' => $phone, 'normalized_value' => $phone, 'confidence_score' => 70, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        }

        return $workshop;
    }

    public function test_the_same_workshop_found_twice_is_merged_and_keeps_every_source(): void
    {
        $rar = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));
        $mapped = $this->workshop('Service Auto Tehnic Șerban', 'Soseaua Cristianului 6, Brasov', 45.66252, 25.5713, '+40722000101');

        $counts = app(WorkshopDeduplicator::class)->run('BV');

        $this->assertSame(1, $counts['auto_merged']);
        $this->assertSame($rar->id, $mapped->fresh()->merged_into_id);
        $this->assertFalse($mapped->fresh()->is_active);
        $this->assertSame(2, WorkshopContact::query()->where('workshop_id', $rar->id)->where('normalized_value', '+40722000101')->count());
        $this->assertSame(WorkshopMatchStatus::AutoMerged, WorkshopMatchCandidate::query()->firstOrFail()->status);
        $this->assertSame(2, Workshop::query()->count());
        $this->assertSame(1, Workshop::query()->canonical()->count());
    }

    public function test_two_workshops_of_one_company_are_never_merged_or_queued(): void
    {
        $this->ingestRar('SERVICE', $this->rarPayload('service_awd'));
        $this->ingestRar('SERVICE', $this->rarPayload('service_branch'));

        $counts = app(WorkshopDeduplicator::class)->run('BV');

        $this->assertSame(0, $counts['auto_merged']);
        $this->assertSame(0, $counts['candidates']);
        $this->assertSame(2, Workshop::query()->canonical()->count());
    }

    public function test_two_companies_at_one_address_wait_for_a_person(): void
    {
        // An owner's service firm and ITP firm at one gate, or two tenants of one yard: the data
        // cannot tell which, so neither is folded into the other automatically.
        $service = WorkshopCompany::query()->create(['legal_name' => 'OEN SERVICE SRL', 'normalized_name' => 'oen service', 'cui' => '50686496']);
        $itp = WorkshopCompany::query()->create(['legal_name' => 'OEN ITP SRL', 'normalized_name' => 'oen itp', 'cui' => '50828019']);
        $this->workshop('OEN SERVICE SRL', 'Bulevardul Muncii nr. 74, Brașov', 45.66252, 25.5713, '+40744705739', ['company_id' => $service->id]);
        $this->workshop('OEN ITP SRL', 'B-dul Muncii 74, Brașov', 45.66252, 25.5713, '+40744705739', ['company_id' => $itp->id]);

        $counts = app(WorkshopDeduplicator::class)->run('BV');

        $this->assertSame(0, $counts['auto_merged']);
        $this->assertSame(1, $counts['candidates']);
        $pair = WorkshopMatchCandidate::query()->firstOrFail();
        $this->assertSame(WorkshopMatchStatus::Pending, $pair->status);
        $this->assertTrue($pair->evidence['different_companies']);
        $this->assertSame(2, Workshop::query()->canonical()->count());
    }

    public function test_branches_at_different_addresses_under_one_copied_point_are_not_queued(): void
    {
        // RAR often gives every branch of a company the office's point.
        $company = WorkshopCompany::query()->create(['legal_name' => 'TIRES AND PARTS SRL', 'normalized_name' => 'tires and parts', 'cui' => '35056829']);
        $this->workshop('TIRES AND PARTS SRL', 'Str. Lungă 10, Brașov', 45.66252, 25.5713, '+40752145615', ['company_id' => $company->id]);
        $this->workshop('TIRES AND PARTS SRL', 'Calea București 181, Brașov', 45.66252, 25.5713, '+40752145615', ['company_id' => $company->id]);

        $counts = app(WorkshopDeduplicator::class)->run('BV');

        $this->assertSame(0, $counts['auto_merged']);
        $this->assertSame(0, $counts['candidates']);
    }

    public function test_a_waiting_pair_that_no_longer_looks_likely_leaves_the_queue(): void
    {
        $a = $this->workshop('Vulcanizare Nord', 'Str. Lungă 10, Brașov', 45.66252, 25.5713, '+40268999999');
        $b = $this->workshop('Auto Tehnic Șerban', 'Str. Lungă 12, Brașov', 45.66260, 25.5714, '+40722000101');
        WorkshopMatchCandidate::query()->create(['workshop_a_id' => min($a->id, $b->id), 'workshop_b_id' => max($a->id, $b->id), 'score' => 70, 'status' => WorkshopMatchStatus::Pending]);

        $counts = app(WorkshopDeduplicator::class)->run('BV');

        $this->assertSame(1, $counts['withdrawn']);
        $this->assertSame(0, WorkshopMatchCandidate::query()->count());
    }

    public function test_neighbours_are_not_merged_for_being_close(): void
    {
        $this->workshop('Vulcanizare Nord', 'Str. Lungă 10, Brașov', 45.66252, 25.5713, '+40268999999');
        $this->workshop('Auto Tehnic Șerban', 'Str. Lungă 12, Brașov', 45.66260, 25.5714, '+40722000101');

        $counts = app(WorkshopDeduplicator::class)->run('BV');

        $this->assertSame(0, $counts['auto_merged']);
        $this->assertSame(0, $counts['candidates']);
    }

    public function test_a_likely_pair_waits_for_a_person(): void
    {
        $this->workshop('Auto Tehnic Șerban', 'Str. Lungă 10, Brașov', 45.66252, 25.5713);
        $this->workshop('Tehnic Șerban Auto Service', 'Str. Lungă 10 bis, Brașov', 45.66300, 25.5720);

        $counts = app(WorkshopDeduplicator::class)->run('BV');

        $this->assertSame(0, $counts['auto_merged']);
        $this->assertSame(1, $counts['candidates']);
        $this->assertSame(WorkshopMatchStatus::Pending, WorkshopMatchCandidate::query()->firstOrFail()->status);
    }

    public function test_a_pair_a_person_kept_apart_is_never_reopened(): void
    {
        $a = $this->workshop('Service Auto Tehnic Șerban', 'Soseaua Cristianului 6, Brasov', 45.66252, 25.5713, '+40722000101');
        $b = $this->workshop('Auto Tehnic Serban', 'Șos. Cristianului nr. 6, Brașov', 45.66253, 25.5713, '+40722000101');
        WorkshopMatchCandidate::query()->create(['workshop_a_id' => min($a->id, $b->id), 'workshop_b_id' => max($a->id, $b->id), 'score' => 95, 'status' => WorkshopMatchStatus::Rejected]);

        app(WorkshopDeduplicator::class)->run('BV');

        $this->assertNull($a->fresh()->merged_into_id);
        $this->assertNull($b->fresh()->merged_into_id);
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        $this->workshop('Service Auto Tehnic Șerban', 'Soseaua Cristianului 6, Brasov', 45.66252, 25.5713, '+40722000101');
        $this->workshop('Auto Tehnic Serban', 'Șos. Cristianului nr. 6, Brașov', 45.66253, 25.5713, '+40722000101');

        $this->artisan('workshops:deduplicate', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(2, Workshop::query()->canonical()->count());
        $this->assertSame(0, WorkshopMatchCandidate::query()->count());
    }
}
