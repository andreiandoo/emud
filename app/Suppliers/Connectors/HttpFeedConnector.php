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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class HttpFeedConnector implements ReportsFeedIssues, SupplierConnector, SupportsConnectionTest
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
        $endpoint = $this->endpointFor($supplier, $mode);

        if (blank($endpoint)) {
            return SupplierConnectionResult::failure("Furnizorul {$supplier->code} nu are endpoint pentru {$mode}.");
        }

        try {
            // Deliberately short, and retry(1) means a single attempt: this runs inside
            // an admin request, so it must fail fast rather than hold a web worker for
            // the import timeout multiplied by the configured retries.
            $response = $this->request($supplier)->timeout(10)->retry(1)->head((string) $endpoint);
        } catch (Throwable $exception) {
            return SupplierConnectionResult::failure($exception->getMessage(), ['endpoint' => $endpoint]);
        }

        $context = ['endpoint' => $endpoint, 'status' => $response->status(), 'content_type' => $response->header('Content-Type')];

        return $response->successful()
            ? SupplierConnectionResult::success("Endpoint accesibil (HTTP {$response->status()}).", $context)
            : SupplierConnectionResult::failure("Endpointul a răspuns cu HTTP {$response->status()}.", $context);
    }

    /** @return iterable<SupplierRecord> */
    public function records(Supplier $supplier, string $mode): iterable
    {
        $this->issues = [];
        $endpoint = $this->endpointFor($supplier, $mode);

        throw_if(blank($endpoint), RuntimeException::class, "Furnizorul {$supplier->code} nu are endpoint pentru {$mode}.");

        $response = $this->request($supplier)->get((string) $endpoint)->throw();
        $stream = fopen('php://temp', 'w+');
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to allocate a temporary HTTP supplier feed stream.');
        }
        fwrite($stream, $response->body());
        rewind($stream);

        try {
            $format = $this->format($supplier, $mode, (string) $endpoint);
            $settings = $this->parserSettings($supplier, $mode);

            foreach ($this->parser->rows($stream, $format, $settings) as $row) {
                $record = $this->mapper->map($supplier, $row, (string) $endpoint);
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

    private function endpointFor(Supplier $supplier, string $mode): ?string
    {
        return match ($mode) {
            'stock' => $supplier->stock_endpoint ?: $supplier->catalog_endpoint,
            'prices' => $supplier->price_endpoint ?: $supplier->catalog_endpoint,
            default => $supplier->catalog_endpoint,
        };
    }

    private function request(Supplier $supplier): PendingRequest
    {
        $credentials = $supplier->credentials ?? [];
        $settings = $supplier->settings ?? [];
        $request = Http::acceptJson()
            ->timeout((int) ($settings['timeout_seconds'] ?? 90))
            ->retry(
                max(1, (int) ($settings['retry_times'] ?? 3)),
                max(100, (int) ($settings['retry_sleep_ms'] ?? 1000)),
            );

        if (isset($credentials['bearer_token'])) {
            $request = $request->withToken($credentials['bearer_token']);
        }

        if (isset($credentials['username'], $credentials['password'])) {
            $request = $request->withBasicAuth($credentials['username'], $credentials['password']);
        }

        return $request->withHeaders($credentials['headers'] ?? []);
    }

    private function format(Supplier $supplier, string $mode, string $endpoint): string
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
            default => strtolower(pathinfo((string) parse_url($endpoint, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'json'),
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
