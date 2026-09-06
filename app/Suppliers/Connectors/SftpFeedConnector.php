<?php

namespace App\Suppliers\Connectors;

use App\Models\Supplier;
use App\Suppliers\Contracts\SupplierConnector;
use App\Suppliers\Contracts\SupplierFeedArtifactProvider;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\Parsing\StructuredSupplierFeedParser;
use App\Suppliers\Parsing\SupplierRecordMapper;
use App\Suppliers\Transport\SftpSupplierFilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;
use Throwable;

class SftpFeedConnector implements SupplierConnector, SupplierFeedArtifactProvider
{
    /** @var array<string, mixed>|null */
    private ?array $artifact = null;

    public function __construct(
        private readonly SftpSupplierFilesystemFactory $filesystemFactory,
        private readonly StructuredSupplierFeedParser $parser,
        private readonly SupplierRecordMapper $mapper,
    ) {}

    /** @return iterable<SupplierRecord> */
    public function records(Supplier $supplier, string $mode): iterable
    {
        $this->artifact = null;
        $endpoint = $this->endpoint($supplier, $mode);
        $disk = $this->filesystemFactory->build($supplier);
        $path = $this->resolvePath($disk, $endpoint);
        $remoteStream = $disk->readStream($path);

        if (! is_resource($remoteStream)) {
            throw new RuntimeException("Unable to open SFTP supplier feed {$path}.");
        }

        $localStream = tmpfile();
        if (! is_resource($localStream)) {
            fclose($remoteStream);
            throw new RuntimeException('Unable to allocate a temporary supplier feed file.');
        }

        $hash = hash_init('sha256');
        $downloaded = 0;

        try {
            while (! feof($remoteStream)) {
                $chunk = fread($remoteStream, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException("Failed while reading SFTP supplier feed {$path}.");
                }
                if ($chunk === '') {
                    continue;
                }

                $downloaded += strlen($chunk);
                hash_update($hash, $chunk);
                if (fwrite($localStream, $chunk) === false) {
                    throw new RuntimeException('Failed while caching the supplier feed locally.');
                }
            }

            rewind($localStream);
            $format = $this->format($supplier, $mode, $path);
            $parserSettings = $this->parserSettings($supplier, $mode);
            $sourceUrl = 'sftp://'.($supplier->credentials['host'] ?? 'supplier').'/'.ltrim($path, '/');

            foreach ($this->parser->rows($localStream, $format, $parserSettings) as $row) {
                $record = $this->mapper->map($supplier, $row, $sourceUrl);
                if ($record) {
                    yield $record;
                }
            }

            $this->artifact = [
                'mode' => $mode,
                'source_path' => $path,
                'filename' => basename($path),
                'size_bytes' => $this->safeFileSize($disk, $path) ?? $downloaded,
                'source_modified_at' => $this->safeLastModified($disk, $path),
                'checksum_sha256' => hash_final($hash),
                'retrieved_at' => now(),
                'metadata' => [
                    'protocol' => 'sftp',
                    'format' => $format,
                    'configured_endpoint' => $endpoint,
                ],
            ];
        } finally {
            fclose($remoteStream);
            fclose($localStream);
        }
    }

    /** @return array<string, mixed>|null */
    public function lastArtifact(): ?array
    {
        return $this->artifact;
    }

    private function endpoint(Supplier $supplier, string $mode): string
    {
        $endpoint = match ($mode) {
            'stock' => $supplier->stock_endpoint ?: $supplier->catalog_endpoint,
            'prices' => $supplier->price_endpoint ?: $supplier->catalog_endpoint,
            default => $supplier->catalog_endpoint,
        };

        $endpoint = trim((string) $endpoint);
        if ($endpoint === '') {
            throw new RuntimeException("SFTP supplier {$supplier->code} has no remote path for {$mode}.");
        }

        $this->assertSafePath($endpoint);

        return $endpoint;
    }

    private function resolvePath(FilesystemAdapter $disk, string $endpoint): string
    {
        if (! strpbrk($endpoint, '*?[')) {
            return ltrim($endpoint, '/');
        }

        $directory = dirname($endpoint);
        $pattern = basename($endpoint);
        if (strpbrk($directory, '*?[')) {
            throw new RuntimeException('SFTP wildcards are supported only in the filename portion of a supplier endpoint.');
        }

        $directory = $directory === '.' ? '' : trim($directory, '/');
        $candidates = array_values(array_filter(
            $disk->files($directory),
            static fn (string $file): bool => fnmatch($pattern, basename($file), FNM_CASEFOLD),
        ));

        if ($candidates === []) {
            throw new RuntimeException("No SFTP supplier feed matches {$endpoint}.");
        }

        usort($candidates, function (string $left, string $right) use ($disk): int {
            $modified = ($this->safeLastModified($disk, $right) ?? 0) <=> ($this->safeLastModified($disk, $left) ?? 0);

            return $modified !== 0 ? $modified : strcmp($right, $left);
        });

        return $candidates[0];
    }

    private function format(Supplier $supplier, string $mode, string $path): string
    {
        $settings = $supplier->settings ?? [];
        $formats = is_array($settings['feed_formats'] ?? null) ? $settings['feed_formats'] : [];
        $format = strtolower(trim((string) ($formats[$mode] ?? $settings['feed_format'] ?? pathinfo($path, PATHINFO_EXTENSION))));

        if ($format === 'txt' && filled($settings['delimiter'] ?? null)) {
            return 'csv';
        }

        if (! in_array($format, ['csv', 'tsv', 'xml', 'json'], true)) {
            throw new RuntimeException("Cannot determine a supported supplier feed format for {$path}.");
        }

        return $format;
    }

    /** @return array<string, mixed> */
    private function parserSettings(Supplier $supplier, string $mode): array
    {
        $settings = $supplier->settings ?? [];
        $perMode = is_array($settings['feed_parser_settings'] ?? null) ? ($settings['feed_parser_settings'][$mode] ?? []) : [];

        return array_merge($settings, is_array($perMode) ? $perMode : []);
    }

    private function assertSafePath(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw new RuntimeException('Supplier SFTP path contains a null byte.');
        }

        $segments = preg_split('#[\\\\/]#', $path) ?: [];
        if (in_array('..', $segments, true)) {
            throw new RuntimeException('Supplier SFTP path traversal is not allowed.');
        }
    }

    private function safeFileSize(FilesystemAdapter $disk, string $path): ?int
    {
        try {
            return $disk->fileSize($path);
        } catch (Throwable) {
            return null;
        }
    }

    private function safeLastModified(FilesystemAdapter $disk, string $path): ?int
    {
        try {
            return $disk->lastModified($path);
        } catch (Throwable) {
            return null;
        }
    }
}
