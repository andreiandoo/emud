<?php

namespace App\Console\Commands;

use App\Catalog\Vehicles\Vin\VpicStandaloneDatabaseInstaller;
use App\Models\CatalogSource;
use Illuminate\Console\Command;
use Throwable;

class InstallVpicStandalone extends Command
{
    protected $signature = 'catalog:vpic:install
        {--archive= : Use an already downloaded official vPIC .custom.zip archive}
        {--force : Reinstall even if the current release is already installed}
        {--keep-archive : Keep a newly downloaded ZIP after successful installation}';

    protected $description = 'Discover, download and atomically install the latest official NHTSA vPIC PostgreSQL standalone database.';

    public function handle(VpicStandaloneDatabaseInstaller $installer): int
    {
        $source = CatalogSource::query()->where('code', 'VPIC')->first();
        if (! $source) {
            $this->error('VPIC catalog source is not configured. Run the catalog source seeder first.');

            return self::FAILURE;
        }

        $archive = $this->option('archive');
        $archivePath = is_string($archive) && trim($archive) !== '' ? realpath($archive) : null;
        if ($archive && $archivePath === false) {
            $this->error('The supplied --archive file does not exist.');

            return self::FAILURE;
        }

        try {
            $this->info('Discovering the latest official NHTSA vPIC PostgreSQL release...');
            $result = $installer->install(
                $source,
                $archivePath ?: null,
                (bool) $this->option('force'),
                (bool) $this->option('keep-archive'),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result['status'] === 'already_installed') {
            $this->info("vPIC {$result['version']} is already installed.");

            return self::SUCCESS;
        }

        $this->info("Installed vPIC {$result['version']} ({$result['release_key']}).");
        $this->line('VIN resolver mode is now postgres_then_http.');

        return self::SUCCESS;
    }
}
