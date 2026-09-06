<?php

namespace App\Catalog\Sources\Connectors;

use App\Catalog\Sources\Contracts\CatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class StreamingCsvCatalogSourceConnector implements CatalogSourceConnector
{
    public function records(CatalogSource $source, string $mode = 'catalog'): iterable
    {
        $endpoint = $source->catalog_endpoint ?: $source->base_url;
        throw_if(blank($endpoint), RuntimeException::class, "Catalog source {$source->code} has no endpoint.");

        $download = tempnam(sys_get_temp_dir(), 'catalog-source-');
        throw_if($download === false, RuntimeException::class, 'Could not create catalog download temp file.');

        try {
            $this->request($source)->withOptions(['sink' => $download])->get($endpoint)->throw();
            $csvPath = $this->resolveCsvPath($download, $source);
            $handle = fopen($csvPath, 'rb');
            throw_if($handle === false, RuntimeException::class, 'Could not open downloaded CSV source.');

            try {
                $delimiter = (string) ($source->settings['delimiter'] ?? ',');
                $headers = fgetcsv($handle, separator: $delimiter) ?: [];
                $headers = array_map(fn ($value) => trim((string) $value), $headers);

                while (($values = fgetcsv($handle, separator: $delimiter)) !== false) {
                    if (count($headers) !== count($values)) {
                        continue;
                    }
                    yield array_combine($headers, $values);
                }
            } finally {
                fclose($handle);
                if ($csvPath !== $download && is_file($csvPath)) {
                    @unlink($csvPath);
                }
            }
        } finally {
            if (is_file($download)) {
                @unlink($download);
            }
        }
    }

    public function testConnection(CatalogSource $source): array
    {
        $endpoint = $source->catalog_endpoint ?: $source->base_url;
        $response = $this->request($source)->head((string) $endpoint);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
        ];
    }

    private function request(CatalogSource $source): PendingRequest
    {
        $credentials = $source->credentials ?? [];
        $request = Http::timeout((int) ($source->settings['timeout_seconds'] ?? 900))
            ->retry((int) ($source->settings['retry_times'] ?? 3), (int) ($source->settings['retry_sleep_ms'] ?? 2000));

        if (isset($credentials['bearer_token'])) {
            $request = $request->withToken($credentials['bearer_token']);
        }

        return $request->withHeaders($credentials['headers'] ?? []);
    }

    private function resolveCsvPath(string $download, CatalogSource $source): string
    {
        if (($source->settings['compression'] ?? null) !== 'zip') {
            return $download;
        }

        throw_unless(class_exists(ZipArchive::class), RuntimeException::class, 'ZIP extension is required for this source.');
        $zip = new ZipArchive();
        throw_unless($zip->open($download) === true, RuntimeException::class, 'Could not open source ZIP archive.');

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name && str_ends_with(strtolower($name), '.csv')) {
                    $contents = $zip->getFromIndex($i);
                    throw_if($contents === false, RuntimeException::class, 'Could not extract CSV from source ZIP.');
                    $csvPath = tempnam(sys_get_temp_dir(), 'catalog-csv-');
                    throw_if($csvPath === false, RuntimeException::class, 'Could not create CSV temp file.');
                    file_put_contents($csvPath, $contents);
                    return $csvPath;
                }
            }
        } finally {
            $zip->close();
        }

        throw new RuntimeException('No CSV file found in source ZIP.');
    }
}
