<?php

namespace Tests\Feature;

use App\Enums\CatalogRightsClass;
use App\Enums\SupplierProtocol;
use App\Models\Brand;
use App\Models\CatalogPart;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOnboardingCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fully_configured_supplier_passes(): void
    {
        $this->supplier();

        $this->artisan('suppliers:onboarding-check AVEX')
            ->expectsOutputToContain('pot produce piese canonice')
            ->assertSuccessful();
    }

    /**
     * Promotion that is switched off makes the job return without writing anything or logging
     * a reason, which is the single easiest way to spend an afternoon wondering why a feed
     * imported cleanly and produced no parts.
     */
    public function test_promotion_switched_off_is_reported_as_blocking(): void
    {
        $this->supplier(['settings' => ['technical_promotion_enabled' => false, 'technical_promotion_create_parts' => true]]);

        $this->artisan('suppliers:onboarding-check AVEX')
            ->expectsOutputToContain('technical_promotion_enabled')
            ->assertFailed();
    }

    public function test_missing_derived_data_rights_is_reported_as_blocking(): void
    {
        $this->supplier(['allow_derived_data' => false]);

        $this->artisan('suppliers:onboarding-check AVEX')
            ->expectsOutputToContain('allow_derived_data')
            ->assertFailed();
    }

    /**
     * Augment-only is the right setting for every supplier after the first, but it cannot be
     * the setting for the first one: there is nothing yet to attach its products to.
     */
    public function test_augment_only_blocks_while_the_parts_catalogue_is_empty(): void
    {
        $this->supplier(['settings' => ['technical_promotion_enabled' => true, 'technical_promotion_create_parts' => false]]);

        $this->artisan('suppliers:onboarding-check AVEX')
            ->expectsOutputToContain('Catalogul de piese e gol')
            ->assertFailed();
    }

    public function test_augment_only_is_allowed_once_parts_exist(): void
    {
        $this->supplier(['settings' => ['technical_promotion_enabled' => true, 'technical_promotion_create_parts' => false]]);
        $brand = Brand::query()->create(['name' => 'MAHLE', 'slug' => 'mahle', 'is_active' => true]);
        CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'brand_id' => $brand->id,
            'mpn_raw' => 'OC 123',
            'mpn_normalized' => 'OC123',
            'name' => 'Oil filter',
        ]);

        $this->artisan('suppliers:onboarding-check AVEX')
            ->expectsOutputToContain('augment-only')
            ->assertSuccessful();
    }

    /**
     * A comma-separated OE column mapped without its delimiter becomes one reference holding
     * the entire string, and nothing in the import reports it.
     */
    public function test_a_list_field_without_its_delimiter_is_flagged(): void
    {
        $this->supplier([
            'field_mapping' => $this->mapping() + ['oe_numbers' => 'oem_refs'],
            'settings' => ['technical_promotion_enabled' => true, 'technical_promotion_create_parts' => true],
        ]);

        $this->artisan('suppliers:onboarding-check AVEX')
            ->expectsOutputToContain('Delimitatori de listă')
            ->assertSuccessful();
    }

    public function test_an_unmapped_identity_is_reported_as_blocking(): void
    {
        $this->supplier(['field_mapping' => ['external_id' => 'sku', 'name' => 'title']]);

        $this->artisan('suppliers:onboarding-check AVEX')
            ->expectsOutputToContain('insufficient_identity')
            ->assertFailed();
    }

    /**
     * The prospect list runs to forty-odd suppliers. A seven-row table each scrolls the useful
     * part off the screen, so --all answers "which one can I onboard next" instead.
     */
    public function test_listing_every_supplier_stays_compact(): void
    {
        $this->supplier();
        $this->supplier(['code' => 'DELDO', 'name' => 'Deldo', 'allow_derived_data' => false]);

        $this->artisan('suppliers:onboarding-check --all')
            ->expectsOutputToContain('1 blocaje')
            ->doesntExpectOutputToContain('Produsele devin blocked_rights')
            ->assertFailed();
    }

    /** @param array<string, mixed> $overrides */
    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::query()->create(array_replace([
            'name' => 'AVEX',
            'code' => 'AVEX',
            'protocol' => SupplierProtocol::Xml,
            'default_currency' => 'RON',
            'timezone' => 'Europe/Bucharest',
            'priority' => 10,
            'data_rights_class' => CatalogRightsClass::PermissionedRedistributable,
            'allow_internal_data' => true,
            'allow_ecommerce_data' => true,
            'allow_derived_data' => true,
            'allow_api_redistribution' => false,
            'attribution_required' => false,
            'field_mapping' => $this->mapping(),
            'settings' => ['technical_promotion_enabled' => true, 'technical_promotion_create_parts' => true],
            'is_active' => true,
        ], $overrides));
    }

    /** @return array<string, string> */
    private function mapping(): array
    {
        return [
            'external_id' => 'sku',
            'name' => 'title',
            'brand' => 'manufacturer',
            'manufacturer_part_number' => 'mpn',
            'ean' => 'barcode',
        ];
    }
}
