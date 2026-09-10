<?php

namespace App\Console\Commands;

use App\Workshops\Geocoding\WorkshopGeocoder;
use App\Workshops\Support\RomanianCounties;
use Illuminate\Console\Command;

class GeocodeWorkshopAddresses extends Command
{
    protected $signature = 'workshops:geocode {--limit=200 : At most this many workshops} {--county= : Only one county (code or name)}';

    protected $description = 'Geocode workshops without a street-level point, through the configured geocoder (none by default).';

    public function handle(WorkshopGeocoder $geocoder): int
    {
        if (! $geocoder->isConfigured()) {
            $this->warn('No geocoder is configured (WORKSHOPS_GEOCODER=nominatim with WORKSHOPS_NOMINATIM_URL). Addresses stay pending; nothing was guessed.');

            return self::SUCCESS;
        }

        $county = $this->option('county') !== null ? RomanianCounties::resolve($this->option('county')) : null;
        $counts = $geocoder->run((int) $this->option('limit'), $county);

        $this->info("{$counts['attempted']} tried: {$counts['located']} placed at street level, {$counts['approximate']} at town level, {$counts['failed']} not placed.");

        return self::SUCCESS;
    }
}
