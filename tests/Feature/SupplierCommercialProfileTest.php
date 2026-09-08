<?php

namespace Tests\Feature;

use App\Enums\SupplierCapability;
use App\Enums\SupplierOnboardingStatus;
use App\Livewire\Admin\Suppliers\SupplierEditor;
use App\Livewire\Admin\Suppliers\SuppliersIndex;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SupplierProspectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierCommercialProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_prospects_are_seeded_without_activating_anything(): void
    {
        $this->seed(SupplierProspectSeeder::class);

        $avex = Supplier::query()->where('code', 'AVEX')->sole();

        $this->assertFalse($avex->is_active);
        $this->assertSame(SupplierOnboardingStatus::NotStarted, $avex->onboarding_status);
        $this->assertSame('unknown_pending_review', $avex->data_rights_class->value);
        $this->assertSame([], $avex->credentials);
        $this->assertSame(91, $avex->qualification_score);
        $this->assertSame('RO', $avex->country_code);
        $this->assertNotEmpty($avex->commercial_profile['sources']);

        // US suppliers are market intelligence for a Romania-first launch, not
        // default cross-border dropship sources.
        $this->assertSame(
            SupplierOnboardingStatus::OnHold,
            Supplier::query()->where('code', 'TURN14')->sole()->onboarding_status,
        );

        // 4CARS publicly limits dropshipping to Slovakia.
        $fourCars = Supplier::query()->where('code', '4CARS')->sole();
        $this->assertSame(['SK'], $fourCars->allowed_countries);
        $this->assertFalse($fourCars->shipsTo('RO'));
        $this->assertTrue($fourCars->shipsTo('sk'));
    }

    public function test_seeding_twice_neither_duplicates_nor_overwrites_operator_edits(): void
    {
        $this->seed(SupplierProspectSeeder::class);

        Supplier::query()->where('code', 'TASY')->update([
            'onboarding_status' => SupplierOnboardingStatus::Approved,
            'dropship_fee' => 4.50,
        ]);

        $this->seed(SupplierProspectSeeder::class);

        $tasy = Supplier::query()->where('code', 'TASY')->sole();
        $this->assertSame(SupplierOnboardingStatus::Approved, $tasy->onboarding_status);
        $this->assertSame('4.50', $tasy->dropship_fee);
        $this->assertSame(1, Supplier::query()->where('code', 'TASY')->count());
    }

    public function test_capabilities_are_tri_state_and_only_yes_is_usable(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Tri State',
            'code' => 'TRISTATE',
            'protocol' => 'xml',
            'supports_catalog' => true,
            'supports_order_api' => false,
            // supports_tracking_api deliberately left unset: not established yet.
        ]);

        $this->assertTrue($supplier->can(SupplierCapability::Catalog));
        $this->assertFalse($supplier->can(SupplierCapability::OrderApi));
        $this->assertFalse($supplier->can(SupplierCapability::TrackingApi));
        $this->assertNull($supplier->capabilities()['supports_tracking_api']);
        $this->assertFalse($supplier->capabilities()['supports_order_api']);
    }

    public function test_blind_fulfilment_requires_both_blind_shipping_and_no_supplier_invoice(): void
    {
        $supplier = new Supplier(['blind_shipping' => true]);
        $this->assertFalse($supplier->hasConfirmedBlindFulfilment(), 'An unanswered invoice question must not count as confirmed.');

        $supplier->supplier_invoice_in_parcel = true;
        $this->assertFalse($supplier->hasConfirmedBlindFulfilment());

        $supplier->supplier_invoice_in_parcel = false;
        $this->assertTrue($supplier->hasConfirmedBlindFulfilment());
    }

    public function test_admin_can_record_commercial_terms_and_capabilities(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(SupplierEditor::class)
            ->set('name', 'Off-road Wholesaler')
            ->set('code', 'offroad_wholesaler')
            ->set('protocol', 'xml')
            ->set('supplierType', 'wholesaler')
            ->set('onboardingStatus', 'sample_received')
            ->set('strategicRole', 'specialist')
            ->set('countryCode', 'de')
            ->set('website', 'https://example.test')
            ->set('qualificationScore', 86)
            ->set('readinessScore', 4)
            ->set('offroadFitScore', 84)
            ->set('capabilities.supports_catalog', 'yes')
            ->set('capabilities.supports_order_api', 'no')
            ->set('fulfilment.blind_shipping', 'yes')
            ->set('fulfilment.supplier_invoice_in_parcel', 'no')
            ->set('dropshipFee', '3.90')
            ->set('minimumOrderValue', '59')
            ->set('termsCurrency', 'eur')
            ->set('returnWindowDays', 14)
            ->set('returnFreightPayer', 'reseller')
            ->set('allowedCountries', 'ro, bg; hu ro')
            ->set('excludedCountries', 'ua')
            ->set('nextAction', 'Cere mostra de feed.')
            ->call('save')
            ->assertHasNoErrors();

        $supplier = Supplier::query()->where('code', 'OFFROAD_WHOLESALER')->sole();

        $this->assertSame('DE', $supplier->country_code);
        $this->assertSame(SupplierOnboardingStatus::SampleReceived, $supplier->onboarding_status);
        $this->assertTrue($supplier->can(SupplierCapability::Catalog));
        $this->assertFalse($supplier->supports_order_api);
        $this->assertNull($supplier->supports_tracking_api, 'Untouched capabilities must stay unknown rather than becoming a no.');
        $this->assertTrue($supplier->hasConfirmedBlindFulfilment());
        $this->assertSame('3.90', $supplier->dropship_fee);
        $this->assertSame('EUR', $supplier->terms_currency);
        $this->assertSame(['RO', 'BG', 'HU'], $supplier->allowed_countries);
        $this->assertFalse($supplier->shipsTo('UA'));
        $this->assertTrue($supplier->shipsTo('RO'));
        $this->assertFalse($supplier->is_active, 'Recording commercial terms must never activate a feed.');
    }

    public function test_index_separates_configured_feeds_from_commercial_prospects(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Supplier::query()->create([
            'name' => 'Approved Feed', 'code' => 'APPROVED', 'protocol' => 'xml',
            'onboarding_status' => SupplierOnboardingStatus::Approved, 'country_code' => 'RO',
        ]);
        Supplier::query()->create([
            'name' => 'Cold Prospect', 'code' => 'PROSPECT', 'protocol' => 'manual',
            'onboarding_status' => SupplierOnboardingStatus::NotStarted, 'country_code' => 'PL',
        ]);

        Livewire::actingAs($admin)->test(SuppliersIndex::class)
            ->assertViewHas('configured', fn ($configured): bool => $configured->pluck('code')->all() === ['APPROVED'])
            ->assertViewHas('prospects', fn ($prospects): bool => $prospects->pluck('code')->all() === ['PROSPECT'])
            ->set('country', 'PL')
            ->assertViewHas('configured', fn ($configured): bool => $configured->isEmpty())
            ->assertViewHas('prospects', fn ($prospects): bool => $prospects->pluck('code')->all() === ['PROSPECT'])
            ->set('country', '')
            ->set('search', 'approved')
            ->assertViewHas('configured', fn ($configured): bool => $configured->pluck('code')->all() === ['APPROVED'])
            ->assertViewHas('prospects', fn ($prospects): bool => $prospects->isEmpty());
    }
}
