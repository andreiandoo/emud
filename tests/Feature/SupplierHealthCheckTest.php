<?php

namespace Tests\Feature;

use App\Enums\SyncStatus;
use App\Models\Supplier;
use App\Models\SupplierSyncRun;
use App\Models\SupplierSyncSchedule;
use App\Models\User;
use App\Notifications\SupplierFeedUnhealthy;
use App\Suppliers\SupplierHealthInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_supplier_with_no_scheduled_modes_is_not_judged_on_them(): void
    {
        $supplier = $this->supplier(['last_successful_sync_at' => now()]);

        $report = app(SupplierHealthInspector::class)->inspect($supplier->fresh());

        $this->assertTrue($report['healthy'], 'Nobody runs a stock feed for a supplier that has none scheduled.');
    }

    public function test_a_scheduled_feed_that_never_ran_is_reported(): void
    {
        $supplier = $this->supplier(['last_successful_sync_at' => now()]);
        $this->schedule($supplier, 'stock');

        $report = app(SupplierHealthInspector::class)->inspect($supplier->fresh());

        $this->assertFalse($report['healthy']);
        $this->assertContains('stock_never_ran', array_column($report['issues'], 'code'));
    }

    public function test_a_stale_feed_is_reported_even_when_its_last_run_succeeded(): void
    {
        $supplier = $this->supplier(['last_successful_sync_at' => now()->subDays(5)]);
        $this->schedule($supplier, 'stock');
        $this->run($supplier, 'stock', SyncStatus::Completed, finishedAt: now()->subDays(5));

        $report = app(SupplierHealthInspector::class)->inspect($supplier->fresh());

        $this->assertFalse($report['healthy']);
        $this->assertContains('stock_stale', array_column($report['issues'], 'code'));
    }

    public function test_a_guarded_run_is_surfaced_as_needing_attention(): void
    {
        $supplier = $this->supplier(['last_successful_sync_at' => now()]);
        $this->schedule($supplier, 'catalog');
        $this->run($supplier, 'catalog', SyncStatus::Completed, finishedAt: now()->subMinutes(30));
        $this->run($supplier, 'catalog', SyncStatus::AbortedGuard, finishedAt: now()->subMinutes(5));

        $report = app(SupplierHealthInspector::class)->inspect($supplier->fresh());

        $this->assertFalse($report['healthy']);
        $this->assertContains('catalog_last_run_aborted_guard', array_column($report['issues'], 'code'));
    }

    public function test_a_high_error_rate_is_reported(): void
    {
        $supplier = $this->supplier(['last_successful_sync_at' => now()]);
        $this->schedule($supplier, 'catalog');
        $this->run($supplier, 'catalog', SyncStatus::Completed, finishedAt: now()->subMinutes(5), received: 1000, failed: 300);

        $report = app(SupplierHealthInspector::class)->inspect($supplier->fresh());

        $this->assertContains('catalog_error_rate', array_column($report['issues'], 'code'));
    }

    public function test_the_command_notifies_administrators_once_per_problem(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $this->schedule($supplier, 'catalog');

        $this->artisan('suppliers:health-check --notify')->assertSuccessful();
        $this->artisan('suppliers:health-check --notify')->assertSuccessful();

        Notification::assertSentToTimes($admin, SupplierFeedUnhealthy::class, 1);
    }

    public function test_the_command_succeeds_when_every_supplier_is_healthy(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        $this->supplier(['last_successful_sync_at' => now()]);

        $this->artisan('suppliers:health-check --notify')->assertSuccessful();

        Notification::assertNothingSent();
    }

    private function supplier(array $attributes = []): Supplier
    {
        return Supplier::query()->create([
            'name' => 'Health Feed',
            'code' => 'HEALTH',
            'protocol' => 'json',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function schedule(Supplier $supplier, string $mode): void
    {
        SupplierSyncSchedule::query()->create([
            'supplier_id' => $supplier->id,
            'mode' => $mode,
            'cron_expression' => '*/15 * * * *',
            'is_enabled' => true,
        ]);
    }

    private function run(Supplier $supplier, string $mode, SyncStatus $status, \DateTimeInterface $finishedAt, int $received = 10, int $failed = 0): void
    {
        SupplierSyncRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'mode' => $mode,
            'status' => $status,
            'received_count' => $received,
            'failed_count' => $failed,
            'started_at' => $finishedAt,
            'finished_at' => $finishedAt,
        ]);
    }
}
