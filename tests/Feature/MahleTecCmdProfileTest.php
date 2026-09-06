<?php

namespace Tests\Feature;

use App\Enums\CatalogRightsClass;
use App\Enums\SupplierProtocol;
use App\Models\Supplier;
use Database\Seeders\SupplierProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MahleTecCmdProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_safe_inactive_and_matches_official_cadence(): void
    {
        $this->seed(SupplierProfileSeeder::class);

        $supplier = Supplier::query()->where('code', 'MAHLE_TECCMD')->with('syncSchedules')->sole();

        $this->assertSame(SupplierProtocol::Sftp, $supplier->protocol);
        $this->assertSame(CatalogRightsClass::CommerceOnly, $supplier->data_rights_class);
        $this->assertFalse($supplier->is_active);
        $this->assertTrue($supplier->allow_internal_data);
        $this->assertTrue($supplier->allow_ecommerce_data);
        $this->assertFalse($supplier->allow_derived_data);
        $this->assertFalse($supplier->allow_api_redistribution);
        $this->assertNull($supplier->catalog_endpoint);
        $this->assertNull($supplier->price_endpoint);
        $this->assertNull($supplier->stock_endpoint);
        $this->assertSame([], $supplier->field_mapping);
        $this->assertSame('requires_customer_access_and_sample_files', $supplier->settings['profile_status']);
        $this->assertCount(3, $supplier->syncSchedules);
        $this->assertTrue($supplier->syncSchedules->every(fn ($schedule): bool => ! $schedule->is_enabled));
        $this->assertSame('5 * * * *', $supplier->syncSchedules->firstWhere('mode', 'stock')->cron_expression);
    }

    public function test_reseeding_never_overwrites_real_customer_configuration(): void
    {
        $this->seed(SupplierProfileSeeder::class);
        $supplier = Supplier::query()->where('code', 'MAHLE_TECCMD')->sole();
        $supplier->update([
            'catalog_endpoint' => 'customer/material-master.csv',
            'credentials' => ['host' => 'sftp.customer.example', 'username' => 'account', 'password' => 'secret'],
            'field_mapping' => ['external_id' => 'MaterialNumber'],
            'is_active' => true,
        ]);
        $supplier->syncSchedules()->where('mode', 'stock')->update(['is_enabled' => true, 'cron_expression' => '15 * * * *']);

        $this->seed(SupplierProfileSeeder::class);

        $supplier->refresh();
        $this->assertSame('customer/material-master.csv', $supplier->catalog_endpoint);
        $this->assertSame('sftp.customer.example', $supplier->credentials['host']);
        $this->assertSame('MaterialNumber', $supplier->field_mapping['external_id']);
        $this->assertTrue($supplier->is_active);
        $this->assertTrue($supplier->syncSchedules()->where('mode', 'stock')->sole()->is_enabled);
        $this->assertSame('15 * * * *', $supplier->syncSchedules()->where('mode', 'stock')->sole()->cron_expression);
    }
}
