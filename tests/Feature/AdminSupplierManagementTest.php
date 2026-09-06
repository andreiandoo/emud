<?php

namespace Tests\Feature;

use App\Livewire\Admin\Suppliers\SupplierEditor;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_rights_aware_sftp_supplier_with_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(SupplierEditor::class)
            ->set('name', 'Manufacturer Feed')
            ->set('code', 'manufacturer_feed')
            ->set('protocol', 'sftp')
            ->set('catalogEndpoint', 'exports/material-*.csv')
            ->set('stockEndpoint', 'exports/stock.csv')
            ->set('priceEndpoint', 'exports/prices.csv')
            ->set('rightsClass', 'commerce_only')
            ->set('allowInternal', true)
            ->set('allowEcommerce', true)
            ->set('allowDerived', false)
            ->set('allowApiRedistribution', false)
            ->set('credentialsJson', json_encode(['host' => 'sftp.example.test', 'host_fingerprint' => 'SHA256:example'], JSON_THROW_ON_ERROR))
            ->set('settingsJson', json_encode(['feed_format' => 'csv'], JSON_THROW_ON_ERROR))
            ->set('mappingJson', json_encode(['external_id' => 'article', 'name' => 'description'], JSON_THROW_ON_ERROR))
            ->set('schedules.catalog', ['cron' => '10 2 * * *', 'timezone' => 'Europe/Bucharest', 'enabled' => true])
            ->set('schedules.prices', ['cron' => '40 1 * * *', 'timezone' => 'Europe/Bucharest', 'enabled' => true])
            ->set('schedules.stock', ['cron' => '5 * * * *', 'timezone' => 'Europe/Bucharest', 'enabled' => true])
            ->call('save')
            ->assertHasNoErrors();

        $supplier = Supplier::query()->where('code', 'MANUFACTURER_FEED')->sole();
        $this->assertSame('sftp.example.test', $supplier->credentials['host']);
        $this->assertTrue($supplier->allow_ecommerce_data);
        $this->assertFalse($supplier->allow_api_redistribution);
        $this->assertSame('article', $supplier->field_mapping['external_id']);
        $this->assertCount(3, $supplier->syncSchedules()->where('is_enabled', true)->get());
        $this->assertSame('5 * * * *', $supplier->syncSchedules()->where('mode', 'stock')->sole()->cron_expression);
    }
}
