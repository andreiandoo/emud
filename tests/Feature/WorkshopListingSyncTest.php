<?php

namespace Tests\Feature;

use App\Directory\WorkshopListingSync;
use App\Livewire\Admin\ServiceShopEditor;
use App\Models\ServiceShop;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\Workshop;
use App\Models\WorkshopAuthorization;
use App\Models\WorkshopContact;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopService;
use App\Models\WorkshopSourceLink;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Classification\ServiceTaxonomy;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class WorkshopListingSyncTest extends TestCase
{
    use RefreshDatabase;

    private WorkshopDataSource $rar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceCatalogSeeder::class);
        $this->rar = WorkshopDataSource::create([
            'key' => 'rar_service',
            'name' => 'RAR · service auto',
            'type' => 'registry',
            'is_public_output_allowed' => true,
        ]);
    }

    public function test_an_authorised_workshop_with_a_phone_becomes_a_published_listing(): void
    {
        VehicleMake::create(['name' => 'Dacia', 'slug' => 'dacia']);
        VehicleMake::create(['name' => 'Volkswagen', 'slug' => 'volkswagen']);
        $workshop = $this->workshop('SC AUTO SERVICE ANDREI SRL', fourByFour: true);
        $this->service($workshop, 'brakes');
        $this->brands($workshop, ['DACIA', 'VW']);

        app(WorkshopListingSync::class)->run();

        $shop = ServiceShop::query()->with(['services', 'makes'])->where('workshop_id', $workshop->id)->sole();

        $this->assertSame('Auto Service Andrei', $shop->name);
        $this->assertSame('auto-service-andrei', $shop->slug);
        $this->assertSame('Cluj-Napoca', $shop->city);
        $this->assertSame('Str. Principala Nr. 1', $shop->address);
        $this->assertSame('published', $shop->status);
        $this->assertSame('0722 111 222', $shop->phone);
        $this->assertContains('4x4', $shop->specialityList());
        $this->assertSame(['rar_authorised'], $shop->certifications);
        $this->assertTrue($shop->services->contains('slug', 'schimb-placute-frana-fata'));
        $this->assertEqualsCanonicalizing(['Dacia', 'Volkswagen'], $shop->makes->pluck('name')->all());
        $this->get($shop->url())->assertOk()->assertSee('Auto Service Andrei');
    }

    /**
     * Until the workshop has agreed to answer them, a request made on its listing would reach
     * the shop, not the workshop.
     */
    public function test_a_listing_from_the_registry_takes_no_appointment_requests(): void
    {
        $this->workshop('AUTO PROGRAMARI SRL');

        app(WorkshopListingSync::class)->run();

        $this->assertFalse(ServiceShop::query()->sole()->accepts_appointments);
    }

    public function test_nothing_is_listed_from_a_source_not_cleared_for_publication(): void
    {
        $this->rar->update(['is_public_output_allowed' => false]);
        $this->workshop('SERVICE PRIVAT SRL');

        app(WorkshopListingSync::class)->run();

        $this->assertSame(0, ServiceShop::query()->count());
    }

    public function test_a_workshop_without_a_phone_or_a_rar_authorisation_is_left_out(): void
    {
        $this->workshop('FARA TELEFON SRL', phone: null);
        $this->workshop('FARA AUTORIZATIE SRL', rar: false);

        app(WorkshopListingSync::class)->run();

        $this->assertSame(0, ServiceShop::query()->count());
    }

    public function test_a_second_run_updates_rather_than_duplicates(): void
    {
        $workshop = $this->workshop('AUTO REPETAT SRL');
        $sync = app(WorkshopListingSync::class);

        $sync->run();
        $workshop->contacts()->update(['value' => '0733 000 999']);
        $totals = $sync->run();

        $this->assertSame(1, ServiceShop::query()->count());
        $this->assertSame('0733 000 999', ServiceShop::query()->sole()->phone);
        $this->assertSame(1, $totals['updated']);
    }

    public function test_a_field_changed_by_hand_is_left_alone_by_the_next_sync(): void
    {
        $workshop = $this->workshop('AUTO TEST SRL');
        $sync = app(WorkshopListingSync::class);
        $sync->run();

        $shop = ServiceShop::query()->sole();
        $shop->update(['name' => 'Auto Test Cluj', 'registry_locked' => ['name']]);

        $workshop->update(['name' => 'AUTO TEST NOU SRL', 'address' => 'STR. NOUA NR. 2']);
        $sync->run();

        $shop->refresh();
        $this->assertSame('Auto Test Cluj', $shop->name);
        $this->assertSame('Str. Noua Nr. 2', $shop->address);
    }

    public function test_two_branches_of_one_company_in_one_town_get_their_own_addresses(): void
    {
        $this->workshop('AUTO FRATII SRL', locality: 'TURDA');
        $this->workshop('AUTO FRATII SRL', locality: 'TURDA', phone: '0744 000 111');

        app(WorkshopListingSync::class)->run();

        $this->assertEqualsCanonicalizing(['auto-fratii', 'auto-fratii-turda'], ServiceShop::query()->pluck('slug')->all());
    }

    public function test_a_listing_is_taken_down_when_its_workshop_leaves_the_registry(): void
    {
        $workshop = $this->workshop('INCHIS SRL');
        $sync = app(WorkshopListingSync::class);
        $sync->run();

        $workshop->update(['is_active' => false]);
        $totals = $sync->run();

        $this->assertSame('draft', ServiceShop::query()->sole()->status);
        $this->assertSame(1, $totals['unpublished']);
    }

    public function test_a_listing_published_by_hand_stays_up_when_its_workshop_leaves(): void
    {
        $workshop = $this->workshop('PASTRAT SRL');
        $sync = app(WorkshopListingSync::class);
        $sync->run();
        ServiceShop::query()->sole()->update(['registry_locked' => ['status']]);

        $workshop->update(['is_active' => false]);
        $sync->run();

        $this->assertSame('published', ServiceShop::query()->sole()->status);
    }

    /**
     * The listing keeps its address on the web, its leads and whatever was edited on it; the
     * workshop it now speaks for does not get a second, blank one.
     */
    public function test_a_merged_duplicate_hands_its_listing_to_the_workshop_it_was_folded_into(): void
    {
        $duplicate = $this->workshop('AUTO DUBLURA SRL');
        $sync = app(WorkshopListingSync::class);
        $sync->run();
        $listing = ServiceShop::query()->sole();

        $survivor = $this->workshop('AUTO PASTRAT SRL');
        $duplicate->update(['merged_into_id' => $survivor->id]);
        $totals = $sync->run();

        $this->assertSame(1, $totals['relinked']);
        $this->assertSame(1, ServiceShop::query()->count());
        $this->assertSame($survivor->id, $listing->refresh()->workshop_id);
        $this->assertSame('published', $listing->status);
    }

    public function test_editing_a_registry_field_in_the_admin_takes_it_over(): void
    {
        $this->workshop('AUTO EDITAT SRL');
        app(WorkshopListingSync::class)->run();
        $shop = ServiceShop::query()->sole();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ServiceShopEditor::class, ['shop' => $shop])
            ->set('phone', '0799 999 999')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['phone'], $shop->refresh()->lockedFields());
    }

    public function test_the_admin_can_hand_a_field_back_to_the_registry(): void
    {
        $this->workshop('AUTO REDAT SRL');
        app(WorkshopListingSync::class)->run();
        $shop = ServiceShop::query()->sole();
        $shop->update(['phone' => '0700 000 000', 'registry_locked' => ['phone']]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ServiceShopEditor::class, ['shop' => $shop])
            ->call('followRegistryAgain')
            ->assertSet('phone', '0722 111 222');

        $this->assertSame([], $shop->refresh()->lockedFields());
    }

    public function test_every_mapped_job_is_in_the_catalogue_and_every_key_in_the_taxonomy(): void
    {
        $jobs = collect(ServiceCatalogSeeder::CATALOGUE)->flatMap(fn (array $category): array => array_keys($category['services']))->all();

        foreach (WorkshopListingSync::SERVICE_MAP as $key => $names) {
            $this->assertTrue(ServiceTaxonomy::exists($key), "Registry service [{$key}] does not exist.");

            foreach ($names as $name) {
                $this->assertContains($name, $jobs, "Catalogue job [{$name}] does not exist.");
            }
        }
    }

    public function test_a_legal_name_becomes_the_name_people_use(): void
    {
        $this->assertSame('Auto Service Andrei', WorkshopListingSync::displayName('SC AUTO SERVICE ANDREI SRL'));
        $this->assertSame('Moto Expert', WorkshopListingSync::displayName('S.C. MOTO EXPERT S.R.L.'));
        $this->assertSame('Service BMW Cluj', WorkshopListingSync::displayName('SERVICE BMW CLUJ SRL'));
        $this->assertSame('Garage Pro', WorkshopListingSync::displayName('Garage Pro'));
    }

    private function workshop(
        string $name,
        string $locality = 'CLUJ-NAPOCA',
        ?string $phone = '0722 111 222',
        bool $rar = true,
        bool $fourByFour = false,
    ): Workshop {
        $workshop = Workshop::create([
            'name' => $name,
            'normalized_name' => Str::lower($name),
            'address' => 'STR. PRINCIPALA NR. 1',
            'locality' => $locality,
            'county' => 'CLUJ',
            'county_code' => 'CJ',
            'is_active' => true,
            'is_rar_authorized' => $rar,
            'supports_4x4' => $fourByFour,
        ]);

        $record = $this->record();

        WorkshopSourceLink::create([
            'workshop_id' => $workshop->id,
            'data_source_id' => $this->rar->id,
            'source_record_id' => $record->id,
            'external_id' => $record->external_id,
            'match_type' => 'identity',
            'match_confidence' => 100,
        ]);

        if ($phone !== null) {
            WorkshopContact::create([
                'workshop_id' => $workshop->id,
                'type' => 'phone',
                'value' => $phone,
                'normalized_value' => (string) preg_replace('/\D/', '', $phone),
                'data_source_id' => $this->rar->id,
                'source_record_id' => $record->id,
                'confidence_score' => 85,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        return $workshop;
    }

    private function record(): WorkshopSourceRecord
    {
        return WorkshopSourceRecord::create([
            'data_source_id' => $this->rar->id,
            'record_type' => 'authorization',
            'external_id' => (string) Str::uuid(),
            'content_hash' => hash('sha256', Str::random(16)),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'fetched_at' => now(),
        ]);
    }

    private function service(Workshop $workshop, string $key): void
    {
        WorkshopService::create([
            'workshop_id' => $workshop->id,
            'service_type_id' => app(ServiceTaxonomy::class)->idFor($key),
            'source_record_id' => $workshop->sourceLinks()->value('source_record_id'),
            'evidence_type' => 'rar_authorization',
            'is_authorized' => true,
            'confidence_score' => 85,
        ]);
    }

    /** @param list<string> $brands */
    private function brands(Workshop $workshop, array $brands): void
    {
        WorkshopAuthorization::create([
            'workshop_id' => $workshop->id,
            'data_source_id' => $this->rar->id,
            'source_record_id' => $this->record()->id,
            'system' => 'SERVICE',
            'is_current' => true,
            'raw_data' => ['brands' => $brands],
        ]);
    }
}
