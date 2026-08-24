<?php

namespace App\Suppliers\Connectors;

use App\Models\Supplier;
use App\Suppliers\Contracts\SupplierConnector;
use App\Suppliers\Data\SupplierRecord;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpFeedConnector implements SupplierConnector
{
    public function records(Supplier $supplier, string $mode): iterable
    {
        $endpoint = match ($mode) {
            'stock' => $supplier->stock_endpoint ?: $supplier->catalog_endpoint,
            'prices' => $supplier->price_endpoint ?: $supplier->catalog_endpoint,
            default => $supplier->catalog_endpoint,
        };

        throw_if(blank($endpoint), RuntimeException::class, "Furnizorul {$supplier->code} nu are endpoint pentru {$mode}.");

        $response = $this->request($supplier)->get($endpoint)->throw();
        $mapping = $supplier->field_mapping ?? [];
        $format = $supplier->protocol->value;

        $rows = match ($format) {
            'csv' => $this->csvRows($response->body(), $supplier->settings ?? []),
            'xml' => $this->xmlRows($response->body(), $supplier->settings ?? []),
            default => data_get($response->json(), $supplier->settings['items_path'] ?? 'data', $response->json()),
        };

        foreach ($rows as $row) {
            $row = (array) $row;
            $externalId = (string) $this->value($row, $mapping, 'external_id');
            $name = (string) $this->value($row, $mapping, 'name');

            if ($externalId === '' || $name === '') {
                continue;
            }

            yield new SupplierRecord(
                externalId: $externalId,
                name: $name,
                sku: $this->nullableString($this->value($row, $mapping, 'sku')),
                ean: $this->nullableString($this->value($row, $mapping, 'ean')),
                manufacturerPartNumber: $this->nullableString($this->value($row, $mapping, 'manufacturer_part_number')),
                description: $this->nullableString($this->value($row, $mapping, 'description')),
                brand: $this->nullableString($this->value($row, $mapping, 'brand')),
                categoryExternalId: $this->nullableString($this->value($row, $mapping, 'category_external_id')),
                costPrice: $this->nullableFloat($this->value($row, $mapping, 'cost_price')),
                recommendedRetailPrice: $this->nullableFloat($this->value($row, $mapping, 'recommended_retail_price')),
                currency: (string) ($this->value($row, $mapping, 'currency') ?: $supplier->default_currency),
                stockQuantity: $this->nullableInt($this->value($row, $mapping, 'stock_quantity')),
                stockStatus: (string) ($this->value($row, $mapping, 'stock_status') ?: 'unknown'),
                leadTimeDays: $this->nullableInt($this->value($row, $mapping, 'lead_time_days')),
                sourceUrl: $this->nullableString($this->value($row, $mapping, 'source_url')),
                images: Arr::wrap($this->value($row, $mapping, 'images') ?? []),
                attributes: (array) ($this->value($row, $mapping, 'attributes') ?? []),
                fitments: (array) ($this->value($row, $mapping, 'fitments') ?? []),
                raw: $row,
            );
        }
    }

    private function request(Supplier $supplier): PendingRequest
    {
        $credentials = $supplier->credentials ?? [];
        $request = Http::acceptJson()->timeout($supplier->settings['timeout_seconds'] ?? 90)->retry(3, 1000);

        if (isset($credentials['bearer_token'])) {
            $request = $request->withToken($credentials['bearer_token']);
        }

        if (isset($credentials['username'], $credentials['password'])) {
            $request = $request->withBasicAuth($credentials['username'], $credentials['password']);
        }

        return $request->withHeaders($credentials['headers'] ?? []);
    }

    private function value(array $row, array $mapping, string $field): mixed
    {
        return data_get($row, $mapping[$field] ?? $field);
    }

    private function csvRows(string $contents, array $settings): iterable
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $contents);
        rewind($handle);
        $delimiter = $settings['delimiter'] ?? ',';
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
        throw_if($xml === false, RuntimeException::class, 'Feed XML invalid.');
        $nodes = $xml->xpath($settings['items_xpath'] ?? '//product') ?: [];

        foreach ($nodes as $node) {
            yield json_decode(json_encode($node, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        }
    }

    private function nullableString(mixed $value): ?string { return filled($value) ? (string) $value : null; }
    private function nullableFloat(mixed $value): ?float { return is_numeric($value) ? (float) $value : null; }
    private function nullableInt(mixed $value): ?int { return is_numeric($value) ? (int) $value : null; }
}
