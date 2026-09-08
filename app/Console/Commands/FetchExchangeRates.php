<?php

namespace App\Console\Commands;

use App\Commerce\ExchangeRateImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchExchangeRates extends Command
{
    protected $signature = 'exchange-rates:fetch {--file= : Import a local XML file instead of downloading}';

    protected $description = 'Import the National Bank of Romania reference rates used to compare supplier costs.';

    public function handle(ExchangeRateImporter $importer): int
    {
        try {
            $xml = $this->option('file')
                ? $this->readFile((string) $this->option('file'))
                : Http::timeout(20)->retry(2, 2000)->get((string) config('emud.pricing.exchange_rates_url'))->throw()->body();

            $result = $importer->importFromXml($xml);
        } catch (Throwable $exception) {
            // Loud on purpose. A stale rate table silently distorts every landed cost,
            // so this must show up as a failed command rather than a quiet no-op.
            $this->error("Importul cursurilor a eșuat: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Cursuri pentru {$result['date']}: {$result['imported']} importate, {$result['skipped']} ignorate.");

        return self::SUCCESS;
    }

    private function readFile(string $path): string
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;

        return $contents !== false ? $contents : throw new \RuntimeException("Fișierul {$path} nu poate fi citit.");
    }
}
