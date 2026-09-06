<?php

namespace App\Suppliers\Connectors;

use App\Enums\SupplierProtocol;
use App\Models\Supplier;
use App\Suppliers\Contracts\SupplierConnector;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\Parsing\StructuredSupplierFeedParser;
use App\Suppliers\Parsing\SupplierRecordMapper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpFeedConnector implements SupplierConnector
{
    public function __construct(
        private readonly StructuredSupplierFeedParser $parser,
        private readonly SupplierRecordMapper $mapper,
    ) {}

    /** @return iterable<SupplierRecord> */
    public function records(Supplier $supplier, string $mode): iterable
    {
        $endpoint = match ($mode) {
            'stock' => $supplier->stock_endpoint ?: $supplier->catalog_endpoint,
            'prices' => $supplier->price_endpoint ?: $supplier->catalog_endpoint,
            default => $supplier->catalog_endpoint,
        };

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
                }
            }
        } finally {
            fclose($stream);
        }
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
