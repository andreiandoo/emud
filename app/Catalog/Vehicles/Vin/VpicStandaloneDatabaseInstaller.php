<?php

namespace App\Catalog\Vehicles\Vin;

use App\Models\CatalogSource;
use App\Models\CatalogSourceRelease;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;
use ZipArchive;

class VpicStandaloneDatabaseInstaller
{
    public function __construct(private readonly VpicStandaloneReleaseDiscovery $discovery) {}

    /** @return array<string, mixed> */
    public function install(CatalogSource $source, ?string $archivePath = null, bool $force = false, bool $keepArchive = false): array
    {
        $release = $this->discovery->discover($source);
        $settings = $source->settings ?? [];
        $installedRelease = (string) ($settings['installed_release'] ?? '');

        if (! $force && $installedRelease === $release['release_key']) {
            return [
                'status' => 'already_installed',
                'release_key' => $release['release_key'],
                'version' => $release['version'],
            ];
        }

        $workingDirectory = storage_path('app/catalog/vpic/'.$release['version']);
        if (! is_dir($workingDirectory) && ! mkdir($workingDirectory, 0775, true) && ! is_dir($workingDirectory)) {
            throw new RuntimeException("Unable to create vPIC working directory: {$workingDirectory}");
        }

        $downloaded = false;
        if ($archivePath === null) {
            $archivePath = $workingDirectory.'/'.$release['filename'];
            $this->download($source, $release['url'], $archivePath);
            $downloaded = true;
        }
        if (! is_file($archivePath)) {
            throw new RuntimeException("vPIC archive does not exist: {$archivePath}");
        }

        $zipChecksum = hash_file('sha256', $archivePath);
        if ($zipChecksum === false) {
            throw new RuntimeException('Unable to calculate vPIC archive checksum.');
        }

        $customPath = $this->extractCustomBackup($archivePath, $workingDirectory);
        $customChecksum = hash_file('sha256', $customPath);
        if ($customChecksum === false) {
            throw new RuntimeException('Unable to calculate vPIC custom backup checksum.');
        }

        $this->restore($source, $customPath);

        $catalogRelease = CatalogSourceRelease::query()->updateOrCreate(
            [
                'catalog_source_id' => $source->id,
                'release_key' => $release['release_key'],
            ],
            [
                'retrieved_at' => $release['retrieved_at'],
                'checksum_sha256' => $customChecksum,
                'raw_object_path' => $release['url'],
                'metadata' => [
                    'provider' => 'National Highway Traffic Safety Administration',
                    'format' => 'PostgreSQL custom backup',
                    'version' => $release['version'],
                    'filename' => $release['filename'],
                    'zip_checksum_sha256' => $zipChecksum,
                    'custom_checksum_sha256' => $customChecksum,
                    'installed_at' => now()->toIso8601String(),
                    'postgres_min_version' => 17,
                ],
            ],
        );

        $settings['installed_release'] = $release['release_key'];
        $settings['installed_version'] = $release['version'];
        $settings['installed_at'] = now()->toIso8601String();
        $settings['resolver_mode'] = 'postgres_then_http';
        $source->forceFill([
            'settings' => $settings,
            'is_active' => true,
            'last_successful_sync_at' => now(),
        ])->save();

        if (! $keepArchive && $downloaded) {
            @unlink($archivePath);
        }
        @unlink($customPath);

        return [
            'status' => 'installed',
            'release_key' => $release['release_key'],
            'version' => $release['version'],
            'catalog_source_release_id' => $catalogRelease->id,
            'checksum_sha256' => $customChecksum,
        ];
    }

    private function download(CatalogSource $source, string $url, string $destination): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== 'vpic.nhtsa.dot.gov' || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Refusing to download vPIC backup from an untrusted host.');
        }

        $settings = $source->settings ?? [];
        $response = Http::withHeaders([
            'User-Agent' => (string) ($settings['user_agent'] ?? 'eMUD-Automotive-Catalog/1.0'),
            'Accept' => 'application/zip,application/octet-stream,*/*',
        ])->timeout((int) ($settings['download_timeout_seconds'] ?? 1800))
            ->retry(3, 3000, throw: false)
            ->withOptions(['sink' => $destination])
            ->get($url);

        $response->throw();
        if (! is_file($destination) || filesize($destination) < 1024 * 1024) {
            @unlink($destination);
            throw new RuntimeException('Downloaded vPIC archive is unexpectedly small or missing.');
        }
    }

    private function extractCustomBackup(string $archivePath, string $workingDirectory): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ext-zip is required to install the vPIC standalone database.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open the vPIC ZIP archive.');
        }

        $candidates = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && preg_match('/^vPICList_lite_\d{4}_\d{2}\.custom$/i', basename($name))) {
                $candidates[] = [$index, basename($name)];
            }
        }

        if (count($candidates) !== 1) {
            $zip->close();
            throw new RuntimeException('vPIC ZIP must contain exactly one expected .custom backup file.');
        }

        [$index, $filename] = $candidates[0];
        $input = $zip->getStream($zip->getNameIndex($index));
        if (! is_resource($input)) {
            $zip->close();
            throw new RuntimeException('Unable to read the vPIC custom backup from ZIP.');
        }

        $destination = $workingDirectory.'/'.$filename;
        $output = fopen($destination, 'wb');
        if (! is_resource($output)) {
            fclose($input);
            $zip->close();
            throw new RuntimeException('Unable to create extracted vPIC backup file.');
        }

        stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);
        $zip->close();

        if (! is_file($destination) || filesize($destination) < 1024 * 1024) {
            @unlink($destination);
            throw new RuntimeException('Extracted vPIC custom backup is unexpectedly small or missing.');
        }

        return $destination;
    }

    private function restore(CatalogSource $source, string $customPath): void
    {
        $settings = $source->settings ?? [];
        $connectionName = (string) ($settings['database_connection'] ?? config('database.default'));
        $schema = (string) ($settings['database_schema'] ?? 'vpic');
        if ($schema !== 'vpic') {
            throw new RuntimeException('The official vPIC backup restores into schema "vpic"; database_schema must be vpic for automated installation.');
        }

        $connection = DB::connection($connectionName);
        $config = (array) config("database.connections.{$connectionName}", []);
        if (($config['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('Automated vPIC installation requires a PostgreSQL Laravel connection.');
        }

        $previousSchema = 'vpic_previous';
        $renamedPrevious = false;
        $connection->select("select pg_advisory_lock(hashtext('emud:vpic:install'))");

        try {
            $this->assertRestoreReadable($source, $customPath);
            $connection->statement("drop schema if exists {$previousSchema} cascade");
            if ($this->schemaExists($connectionName, $schema)) {
                $connection->statement("alter schema {$schema} rename to {$previousSchema}");
                $renamedPrevious = true;
            }

            $result = $this->runPgRestore($source, $config, $customPath);
            if (! $result->successful()) {
                throw new RuntimeException('pg_restore failed: '.trim($result->errorOutput() ?: $result->output()));
            }
            if (! $this->decoderExists($connectionName)) {
                throw new RuntimeException('Restored vPIC schema does not contain spVinDecode.');
            }

            if ($renamedPrevious) {
                $connection->statement("drop schema if exists {$previousSchema} cascade");
            }
        } catch (Throwable $exception) {
            try {
                if ($this->schemaExists($connectionName, 'vpic')) {
                    $connection->statement('drop schema vpic cascade');
                }
                if ($renamedPrevious && $this->schemaExists($connectionName, $previousSchema)) {
                    $connection->statement("alter schema {$previousSchema} rename to vpic");
                }
            } catch (Throwable $rollbackException) {
                report($rollbackException);
            }

            throw $exception;
        } finally {
            $connection->select("select pg_advisory_unlock(hashtext('emud:vpic:install'))");
        }
    }

    private function assertRestoreReadable(CatalogSource $source, string $customPath): void
    {
        $binary = (string) (($source->settings ?? [])['pg_restore_binary'] ?? 'pg_restore');
        $result = Process::timeout(120)->run([$binary, '--list', $customPath]);
        if (! $result->successful() || ! str_contains(strtolower($result->output()), 'schema')) {
            throw new RuntimeException('pg_restore could not validate the vPIC custom backup.');
        }
    }

    /** @param array<string, mixed> $config */
    private function runPgRestore(CatalogSource $source, array $config, string $customPath): ProcessResult
    {
        $binary = (string) (($source->settings ?? [])['pg_restore_binary'] ?? 'pg_restore');
        $host = is_array($config['host'] ?? null) ? (string) ($config['host'][0] ?? '') : (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '5432');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('PostgreSQL database and username are required for vPIC restore.');
        }

        return Process::env(['PGPASSWORD' => $password])
            ->timeout((int) (($source->settings ?? [])['restore_timeout_seconds'] ?? 1800))
            ->run([
                $binary,
                '--host', $host,
                '--port', $port,
                '--username', $username,
                '--dbname', $database,
                '--no-owner',
                '--no-privileges',
                '--exit-on-error',
                $customPath,
            ]);
    }

    private function schemaExists(string $connectionName, string $schema): bool
    {
        $row = DB::connection($connectionName)->selectOne(
            'select exists(select 1 from information_schema.schemata where schema_name = ?) as present',
            [$schema],
        );

        return (bool) ($row->present ?? false);
    }

    private function decoderExists(string $connectionName): bool
    {
        $row = DB::connection($connectionName)->selectOne(
            "select exists(select 1 from information_schema.routines where routine_schema = 'vpic' and lower(routine_name) = 'spvindecode') as present",
        );

        return (bool) ($row->present ?? false);
    }
}
