<?php

namespace App\Suppliers\Connectors;

use App\Enums\SupplierProtocol;
use App\Enums\SupplierSyncErrorType;
use App\Models\Supplier;
use App\Suppliers\Contracts\ReportsFeedIssues;
use App\Suppliers\Contracts\SupplierConnector;
use App\Suppliers\Contracts\SupportsConnectionTest;
use App\Suppliers\Data\SupplierConnectionResult;
use App\Suppliers\Data\SupplierFeedIssue;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\Parsing\StructuredSupplierFeedParser;
use App\Suppliers\Parsing\SupplierRecordMapper;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads a supplier feed from a file on a storage disk rather than over the network.
 *
 * Plenty of suppliers deliver a file by mail or by hand long before they hand over an endpoint,
 * and a local file is also the only way to exercise the full import path — parser, field
 * mapping, delimiters, promotion — without a live account.
 *
 * The endpoint columns hold a path on the disk named by `settings.feed_disk`.
 */
class LocalFileFeedConnector implements ReportsFeedIssues, SupplierConnector, SupportsConnectionTest
{
    /** @var list<SupplierFeedIssue> */
    private array $issues = [];

    public function __construct(
        private readonly StructuredSupplierFeedParser $parser,
        private readonly SupplierRecordMapper $mapper,
    ) {}

    /** @return list<SupplierFeedIssue> */
    public function takeIssues(): array
    {
        $issues = $this->issues;
        $this->issues = [];

        return $issues;
    }

    public function testConnection(Supplier $supplier, string $mode = 'catalog'): SupplierConnectionResult
    {
        $path = $this->pathFor($supplier, $mode);

        if (blank($path)) {
            return SupplierConnectionResult::failure("Furnizorul {$supplier->code} nu are fișier configurat pentru {$mode}.");
        }

        $disk = $this->disk($supplier);
        $context = ['disk' => $this->diskName($supplier), 'path' => $path];

        if (! Storage::disk($disk)->exists($path)) {
            return SupplierConnectionResult::failure("Fișierul {$path} nu există pe discul {$disk}.", $context);
        }

        $size = Storage::disk($disk)->size($path);

        return SupplierConnectionResult::success("Fișier găsit ({$size} octeți).", $context + ['size_bytes' => $size]);
    }

    /** @return iterable<SupplierRecord> */
    public function records(Supplier $supplier, string $mode): iterable
    {
        $this->issues = [];
        $path = $this->pathFor($supplier, $mode);

        throw_if(blank($path), RuntimeException::class, "Furnizorul {$supplier->code} nu are fișier configurat pentru {$mode}.");

        $disk = $this->disk($supplier);
        throw_unless(
            Storage::disk($disk)->exists($path),
            RuntimeException::class,
            "Fișierul {$path} nu există pe discul {$disk}.",
        );

        // Streamed rather than read into a string: a full supplier catalogue is routinely
        // larger than the memory a queue worker should be holding for one feed.
        $stream = Storage::disk($disk)->readStream($path);
        throw_unless(is_resource($stream), RuntimeException::class, "Fișierul {$path} nu a putut fi deschis.");

        try {
            foreach ($this->parser->rows($stream, $this->format($supplier, $mode, (string) $path), $this->parserSettings($supplier, $mode)) as $row) {
                $record = $this->mapper->map($supplier, $row, (string) $path);

                if ($record) {
                    yield $record;

                    continue;
                }

                $this->issues[] = new SupplierFeedIssue(
                    SupplierSyncErrorType::Rejected,
                    'Rândul nu are identificator extern sau denumire, deci nu poate fi mapat.',
                    raw: $row,
                );
            }
        } finally {
            fclose($stream);
        }
    }

    private function pathFor(Supplier $supplier, string $mode): ?string
    {
        return match ($mode) {
            'stock' => $supplier->stock_endpoint ?: $supplier->catalog_endpoint,
            'prices' => $supplier->price_endpoint ?: $supplier->catalog_endpoint,
            default => $supplier->catalog_endpoint,
        };
    }

    private function disk(Supplier $supplier): string
    {
        return $this->diskName($supplier);
    }

    private function diskName(Supplier $supplier): string
    {
        return (string) ($supplier->settings['feed_disk'] ?? 'local');
    }

    private function format(Supplier $supplier, string $mode, string $path): string
    {
        $settings = $supplier->settings ?? [];
        $formats = is_array($settings['feed_formats'] ?? null) ? $settings['feed_formats'] : [];
        $configured = $formats[$mode] ?? $settings['feed_format'] ?? null;

        if (filled($configured)) {
            return strtolower(trim((string) $configured));
        }

        return match ($supplier->protocol) {
            SupplierProtocol::Csv => 'csv',
            SupplierProtocol::Xml => 'xml',
            SupplierProtocol::Json, SupplierProtocol::Api => 'json',
            default => strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'csv'),
        };
    }

    /** @return array<string, mixed> */
    private function parserSettings(Supplier $supplier, string $mode): array
    {
        $settings = $supplier->settings ?? [];
        $perMode = is_array($settings['feed_parser_settings'] ?? null) ? ($settings['feed_parser_settings'][$mode] ?? []) : [];

        return array_merge($settings, is_array($perMode) ? $perMode : []);
    }
}
