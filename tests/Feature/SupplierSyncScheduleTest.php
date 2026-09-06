<?php

namespace Tests\Feature;

use App\Jobs\SyncSupplierFeed;
use App\Models\Supplier;
use App\Models\SupplierSyncSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupplierSyncScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_supplier_schedule_dispatches_once_per_minute(): void
    {
        Queue::fake();
        $supplier = $this->supplier('SCHEDULED');
        $schedule = SupplierSyncSchedule::query()->create([
            'supplier_id' => $supplier->id,
            'mode' => 'stock',
            'cron_expression' => '* * * * *',
            'timezone' => 'Europe/Bucharest',
            'is_enabled' => true,
        ]);

        Artisan::call('suppliers:dispatch-schedules');
        Artisan::call('suppliers:dispatch-schedules');

        Queue::assertPushed(SyncSupplierFeed::class, 1);
        Queue::assertPushed(SyncSupplierFeed::class, fn (SyncSupplierFeed $job): bool => $job->supplierId === $supplier->id && $job->mode === 'stock');
        $this->assertNotNull($schedule->fresh()->last_dispatched_at);
    }

    public function test_legacy_global_command_skips_explicit_schedule_but_manual_supplier_sync_does_not(): void
    {
        Queue::fake();
        $scheduled = $this->supplier('SCHEDULED');
        $fallback = $this->supplier('FALLBACK');
        SupplierSyncSchedule::query()->create([
            'supplier_id' => $scheduled->id,
            'mode' => 'stock',
            'cron_expression' => '5 * * * *',
            'timezone' => 'Europe/Bucharest',
            'is_enabled' => true,
        ]);

        Artisan::call('suppliers:sync', ['--mode' => 'stock']);

        Queue::assertPushed(SyncSupplierFeed::class, fn (SyncSupplierFeed $job): bool => $job->supplierId === $fallback->id && $job->mode === 'stock');
        Queue::assertNotPushed(SyncSupplierFeed::class, fn (SyncSupplierFeed $job): bool => $job->supplierId === $scheduled->id);

        Queue::fake();
        Artisan::call('suppliers:sync', ['supplier' => 'SCHEDULED', '--mode' => 'stock']);
        Queue::assertPushed(SyncSupplierFeed::class, fn (SyncSupplierFeed $job): bool => $job->supplierId === $scheduled->id && $job->mode === 'stock');
    }

    private function supplier(string $code): Supplier
    {
        return Supplier::query()->create([
            'name' => $code,
            'code' => $code,
            'protocol' => 'manual',
            'default_currency' => 'EUR',
            'is_active' => true,
            'data_rights_class' => 'commerce_only',
            'allow_internal_data' => true,
        ]);
    }
}
