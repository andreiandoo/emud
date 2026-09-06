<?php

namespace App\Catalog\Sources\Connectors;

use App\Catalog\Sources\Contracts\CatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpCatalogSourceConnector implements CatalogSourceConnector
{
    public function records(CatalogSource $source, string $mode = 'catalog'): iterable
    {
        $endpoint = $source->catalog_endpoint ?: $source->base_url;
        throw_if(blank($endpoint), RuntimeException::class, "Catalog source {$source->code} has no endpoint.");

        $response = $this->request($source)->get($endpoint)->throw();
        $format = strtolower((string) ($source->settings['format'] ?? 'json'));

        $rows = match ($format) {
            'csv' => $this->csvRows($response->body(), $source->settings ?? []),
            'xml' => $this->xmlRows($response->body(), $source->settings ?? []),
            default => data_get($response->json(), $source->settings['items_path'] ?? 'data', $response->json()),
        };

        foreach ($rows as $row) {
            yield (array) $row;
        }
    }

    public function testConnection(CatalogSource $source): array
    {
        $endpoint = $source->catalog_endpoint ?: $source->base_url;
        throw_if(blank($endpoint), RuntimeException::class, "Catalog source {$source->code} has no endpoint.");

        $response = $this->request($source)->head($endpoint);
        if ($response->status() === 405) {
            $response = $this->request($source)->get($endpoint);
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
        ];
    }

    private function request(CatalogSource $source): PendingRequest
    {
        $credentials = $source->credentials ?? [];
        $request = Http::acceptJson()
            ->timeout((int) ($source->settings['timeout_seconds'] ?? 90))
            ->retry((int) ($source->settings['retry_times'] ?? 3), (int) ($source->settings['retry_sleep_ms'] ?? 1000));

        if (isset($credentials['bearer_token'])) {
            $request = $request->withToken($credentials['bearer_token']);
        }

        if (isset($credentials['username'], $credentials['password'])) {
            $request = $request->withBasicAuth($credentials['username'], $credentials['password']);
        }

        return $request->withHeaders($credentials['headers'] ?? []);
    }

    private function csvRows(string $contents, array $settings): iterable
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $contents);
        rewind($handle);
        $delimiter = (string) ($settings['delimiter'] ?? ',');
        $headers = fgetcsv($handle, separator: $delimiter) ?: [];

        while (($values = fgetcsv($handle, separator: $delimiter)) !== false) {
            if (count($headers) === count($values)) {
                yield array_combine($headers, $values);
            }
        }

        fclose($handle);
    }

    private function xmlRows(string $contents, array $settings): iterable
    {
        $xml = simplexml_load_string($contents, options: LIBXML_NONET | LIBXML_NOCDATA);
        throw_if($xml === false, RuntimeException::class, 'Invalid XML catalog source.');

        foreach ($xml->xpath($settings['items_xpath'] ?? '//item') ?: [] as $node) {
            yield json_decode(json_encode($node, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        }
    }
}
