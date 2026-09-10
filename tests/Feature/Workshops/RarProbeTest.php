<?php

namespace Tests\Feature\Workshops;

use App\Models\WorkshopSourceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class RarProbeTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
    }

    private function counties(array $covasna): array
    {
        $names = ['Alba', 'Arad', 'Arges', 'Bacau', 'Bihor', 'Bistrita-Nasaud', 'Botosani', 'Braila', 'Brasov', 'Bucuresti', 'Buzau', 'Calarasi', 'Caras-Severin', 'Cluj', 'Constanta', 'Dambovita', 'Dolj', 'Galati', 'Giurgiu', 'Gorj', 'Harghita', 'Hunedoara', 'Ialomita', 'Iasi', 'Ilfov', 'Maramures', 'Mehedinti', 'Mures', 'Neamt', 'Olt', 'Prahova', 'Salaj', 'Satu Mare', 'Sibiu', 'Suceava', 'Teleorman', 'Timis', 'Tulcea', 'Valcea', 'Vaslui', 'Vrancea'];

        return array_fill_keys($names, []) + ['Covasna' => $covasna];
    }

    public function test_a_registry_answering_as_expected_passes(): void
    {
        $this->fakeRegistry($this->counties([$this->rarPayload('service_awd')]));

        $this->artisan('workshops:rar:probe')->assertSuccessful();
    }

    public function test_a_registry_whose_answer_changed_shape_fails_loudly(): void
    {
        $this->fakeRegistry($this->counties([['status' => 'ACT', 'system' => 'SERVICE']]));

        $this->artisan('workshops:rar:probe')->expectsOutputToContain('FAILED')->assertFailed();
    }

    public function test_the_probe_stores_nothing(): void
    {
        $this->fakeRegistry($this->counties([$this->rarPayload('service_awd')]));

        $this->artisan('workshops:rar:probe');

        $this->assertSame(0, WorkshopSourceRecord::query()->count());
    }
}
