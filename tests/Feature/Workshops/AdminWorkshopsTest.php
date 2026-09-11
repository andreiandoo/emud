<?php

namespace Tests\Feature\Workshops;

use App\Enums\WorkshopMatchStatus;
use App\Enums\WorkshopRecordMatchStatus;
use App\Livewire\Admin\Workshops\WorkshopReviewQueue;
use App\Livewire\Admin\Workshops\WorkshopsIndex;
use App\Livewire\Admin\Workshops\WorkshopSourcesIndex;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopMatchCandidate;
use App\Models\WorkshopRecordMatch;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\SourceRecordStore;
use App\Workshops\Support\CompanyNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class AdminWorkshopsTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    private User $admin;

    private Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));
        $this->ingestRar('B4', $this->rarPayload('b4_winch'));
    }

    public function test_the_registry_lists_searches_and_explains_what_it_understood(): void
    {
        Livewire::actingAs($this->admin)->test(WorkshopsIndex::class)
            ->assertSee('AUTO TEHNIC ȘERBAN S.R.L.')
            ->assertSee('OFFROAD MONTAJ S.R.L.')
            ->set('q', 'service cutii automate Brașov')
            ->assertSee('Am înțeles')
            ->assertSee('AUTO TEHNIC ȘERBAN S.R.L.')
            ->assertDontSee('OFFROAD MONTAJ S.R.L.')
            ->set('q', '')
            ->set('county', 'CJ')
            ->assertSee('OFFROAD MONTAJ S.R.L.')
            ->assertDontSee('AUTO TEHNIC ȘERBAN S.R.L.');
    }

    public function test_a_workshop_page_shows_its_provenance(): void
    {
        $this->actingAs($this->admin)->get(route('admin.workshops.show', $this->workshop))
            ->assertOk()
            ->assertSee('A1.2.1.3')
            ->assertSee('tracțiune permanentă pe mai multe axe')
            ->assertSee('Punct de lucru (RAR)')
            ->assertSee('rar_service')
            ->assertSee('Tracțiune integrală permanentă');

        $record = $this->workshop->sourceLinks()->firstOrFail()->source_record_id;
        $this->actingAs($this->admin)->get(route('admin.workshops.records.show', $record))->assertOk()->assertSee('OCS.BV.NI.900001');
        $this->actingAs($this->admin)->get(route('admin.workshops.sources'))->assertOk()->assertSee('rar_service');
    }

    public function test_a_person_can_merge_or_keep_apart_a_pair(): void
    {
        $twin = Workshop::query()->create(['name' => 'Service Auto Tehnic Șerban', 'normalized_name' => CompanyNameNormalizer::normalize('Service Auto Tehnic Șerban'), 'county_code' => 'BV', 'is_active' => true]);
        $pair = WorkshopMatchCandidate::query()->create(['workshop_a_id' => $this->workshop->id, 'workshop_b_id' => $twin->id, 'score' => 70, 'status' => WorkshopMatchStatus::Pending]);

        Livewire::actingAs($this->admin)->test(WorkshopReviewQueue::class)
            ->assertSee('Service Auto Tehnic Șerban')
            ->call('merge', $pair->id);

        $this->assertSame($this->workshop->id, $twin->fresh()->merged_into_id);
        $this->assertSame(WorkshopMatchStatus::Confirmed, $pair->fresh()->status);
        $this->assertSame($this->admin->id, $pair->fresh()->reviewed_by);
    }

    public function test_a_mapped_point_is_shown_with_its_address_and_each_candidate_with_its_own(): void
    {
        // A chain's branches share a name: the address and the distance are what tell them apart.
        $near = Workshop::query()->create(['name' => 'BEST TIRES SHOP SRL', 'normalized_name' => 'best tires shop', 'address' => 'Str. Zizinului nr. 110, Brașov', 'locality' => 'Brașov', 'county_code' => 'BV', 'latitude' => 45.6400, 'longitude' => 25.6200, 'coordinates_confidence' => 85, 'is_active' => true]);
        $far = Workshop::query()->create(['name' => 'BEST TIRES SHOP SRL', 'normalized_name' => 'best tires shop', 'address' => 'Calea București nr. 5, Brașov', 'locality' => 'Brașov', 'county_code' => 'BV', 'latitude' => 45.6700, 'longitude' => 25.6200, 'coordinates_confidence' => 85, 'is_active' => true]);
        $record = app(SourceRecordStore::class)->store(WorkshopDataSource::forKey(DataSourceCatalog::OSM), new SourceRecordData(
            recordType: 'poi',
            externalId: 'node/4242',
            payload: ['type' => 'node', 'id' => 4242, 'lat' => 45.6401, 'lng' => 25.6201, 'tags' => ['name' => 'Best Tires Shop', 'shop' => 'tyres', 'addr:street' => 'Strada Zizinului', 'addr:housenumber' => '110', 'addr:city' => 'Brașov']],
        ))->record;
        WorkshopRecordMatch::query()->create(['source_record_id' => $record->id, 'target_type' => WorkshopRecordMatch::TARGET_WORKSHOP, 'status' => WorkshopRecordMatchStatus::Ambiguous, 'score' => 80, 'candidates' => [
            ['workshop_id' => $near->id, 'name' => $near->name, 'score' => 80, 'signals' => ['name_similarity' => 1, 'address_similarity' => 1]],
            ['workshop_id' => $far->id, 'name' => $far->name, 'score' => 75, 'signals' => ['name_similarity' => 1]],
        ]]);

        Livewire::actingAs($this->admin)->test(WorkshopReviewQueue::class)
            ->set('tab', 'records')
            ->assertSee('Strada Zizinului 110, Brașov')
            ->assertSee('Str. Zizinului nr. 110, Brașov')
            ->assertSee('Calea București nr. 5, Brașov')
            ->assertSee('la 14 m de punctul OSM')
            ->assertSee('la 3,3 km de punctul OSM')
            ->assertSee('adresă 100%')
            ->assertSee('vezi în OpenStreetMap');
    }

    public function test_a_source_is_cleared_for_publication_with_a_visible_button(): void
    {
        $source = WorkshopDataSource::forKey(DataSourceCatalog::rarKey('SERVICE'));
        $this->assertFalse($source->is_public_output_allowed);

        Livewire::actingAs($this->admin)->test(WorkshopSourcesIndex::class)
            ->assertSee('Fă publicabilă')
            ->call('togglePublic', $source->id)
            ->assertSee('Fă internă');

        $this->assertTrue($source->fresh()->is_public_output_allowed);
    }

    public function test_the_internal_api_answers_admins_only(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.api.workshops.index', ['q' => 'cutii automate Brașov']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.cui', '20963285')
            ->assertJsonPath('data.0.supports_4x4', true);

        $this->actingAs($this->admin)->getJson(route('admin.api.workshops.show', $this->workshop))
            ->assertOk()
            ->assertJsonPath('data.authorizations.0.system', 'SERVICE')
            ->assertJsonMissingPath('data.authorizations.0.raw_data');

        $this->actingAs($this->admin)->getJson(route('admin.api.workshop-services'))->assertOk()->assertJsonFragment(['key' => '4x4_drivetrain']);

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->getJson(route('admin.api.workshops.index'))->assertForbidden();
    }
}
