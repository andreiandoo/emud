<?php

namespace Tests\Feature\Workshops;

use App\Enums\WorkshopMatchStatus;
use App\Livewire\Admin\Workshops\WorkshopReviewQueue;
use App\Livewire\Admin\Workshops\WorkshopsIndex;
use App\Livewire\Admin\Workshops\WorkshopSourcesIndex;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopMatchCandidate;
use App\Workshops\Ingestion\DataSourceCatalog;
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
