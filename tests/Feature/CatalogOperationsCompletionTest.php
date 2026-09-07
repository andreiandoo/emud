<?php

namespace Tests\Feature;

use App\Catalog\Sources\Contracts\CatalogSourceConnector;
use App\Jobs\SyncCatalogSource;
use App\Livewire\Admin\CatalogPlatform\SourceEditor;
use App\Models\CatalogSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogOperationsCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_database_schema_explorer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/catalog-schema')
            ->assertOk()
            ->assertSee('Database schema explorer')
            ->assertSee('catalog_parts');
    }

    public function test_source_editor_persists_per_mode_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(SourceEditor::class)
            ->set('name', 'Operations Test Source')
            ->set('code', 'operations_test')
            ->set('sourceType', 'test')
            ->set('protocol', 'http')
            ->set('baseUrl', 'https://example.test')
            ->set('rightsClass', 'internal_reference')
            ->set('credentialsJson', '{}')
            ->set('settingsJson', '{}')
            ->set('mappingJson', '{}')
            ->set('capabilitiesJson', '{}')
            ->set('schedules.0.mode', 'catalog')
            ->set('schedules.0.cron_expression', '15 2 * * *')
            ->set('schedules.0.timezone', 'Europe/Bucharest')
            ->set('schedules.0.is_enabled', true)
            ->call('addSchedule')
            ->set('schedules.1.mode', 'delta')
            ->set('schedules.1.cron_expression', '*/30 * * * *')
            ->set('schedules.1.timezone', 'Europe/Bucharest')
            ->set('schedules.1.is_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $source = CatalogSource::query()->where('code', 'OPERATIONS_TEST')->sole();
        $this->assertSame('15 2 * * *', $source->schedules()->where('mode', 'catalog')->sole()->cron_expression);
        $this->assertSame('*/30 * * * *', $source->schedules()->where('mode', 'delta')->sole()->cron_expression);
    }

    public function test_source_editor_rejects_invalid_cron_expression(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(SourceEditor::class)
            ->set('name', 'Bad Cron Source')
            ->set('code', 'bad_cron')
            ->set('sourceType', 'test')
            ->set('protocol', 'http')
            ->set('rightsClass', 'internal_reference')
            ->set('credentialsJson', '{}')
            ->set('settingsJson', '{}')
            ->set('mappingJson', '{}')
            ->set('capabilitiesJson', '{}')
            ->set('schedules.0.mode', 'catalog')
            ->set('schedules.0.cron_expression', 'not a cron')
            ->call('save')
            ->assertHasErrors(['schedules.0.cron_expression']);

        $this->assertDatabaseMissing('catalog_sources', ['code' => 'BAD_CRON']);
    }

    public function test_saved_source_can_test_connection_and_queue_manual_sync(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $source = CatalogSource::query()->create([
            'public_id' => '01TESTSOURCE00000000000000',
            'name' => 'Fake connector source',
            'code' => 'FAKE_CONNECTOR',
            'source_type' => 'test',
            'protocol' => 'http',
            'connector_class' => FakeCatalogSourceConnector::class,
            'rights_class' => 'internal_reference',
            'allow_internal' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)->test(SourceEditor::class, ['source' => $source])
            ->call('testConnection')
            ->assertSet('connectionResult.ok', true)
            ->assertSet('connectionResult.details.status', 204)
            ->call('runNow', 'catalog');

        Queue::assertPushed(SyncCatalogSource::class, fn (SyncCatalogSource $job) => $job->sourceId === $source->id && $job->mode === 'catalog');
    }

    public function test_catalog_system_check_succeeds_after_migrations(): void
    {
        $this->artisan('catalog:system:check')
            ->expectsOutputToContain('catalog')
            ->assertSuccessful();
    }
}

class FakeCatalogSourceConnector implements CatalogSourceConnector
{
    public function records(CatalogSource $source, string $mode = 'catalog'): iterable
    {
        return [];
    }

    public function testConnection(CatalogSource $source): array
    {
        return [
            'ok' => true,
            'status' => 204,
            'content_type' => 'application/json',
            'token' => 'must-not-be-exposed',
        ];
    }
}
