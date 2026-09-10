<?php

namespace App\Console\Commands;

use App\Workshops\Export\WorkshopExporter;
use App\Workshops\Search\WorkshopFilters;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportWorkshops extends Command
{
    protected $signature = 'workshops:export
        {--format=csv : csv or json}
        {--output= : File path (default: storage/app/private/workshops/exports/…)}
        {--county= : County code or name}
        {--city= : Locality}
        {--service= : Service key, e.g. automatic_transmission, 4x4_drivetrain}
        {--capability=* : 4x4, awd_permanent, offroad, ev, hybrid, trucks, itp_4x4}
        {--code= : RAR activity code, e.g. A1.2.1.3}
        {--rar : Only RAR-authorised service workshops}
        {--itp : Only ITP stations}
        {--has-email : Only workshops with an email address}
        {--has-phone : Only workshops with a phone number}
        {--q= : Free text: name, CUI, address, phone}
        {--include-inactive : Include workshops no current source lists}
        {--public : Leave out data from sources not cleared for publication}';

    protected $description = 'Export the workshop registry (or a filtered part of it) to CSV or JSON.';

    public function handle(WorkshopExporter $exporter): int
    {
        $format = strtolower((string) $this->option('format'));

        if (! in_array($format, ['csv', 'json'], true)) {
            $this->error('Use --format=csv or --format=json.');

            return self::FAILURE;
        }

        $filters = WorkshopFilters::fromArray(array_filter([
            'q' => $this->option('q'),
            'county' => $this->option('county'),
            'city' => $this->option('city'),
            'service' => $this->option('service'),
            'capability' => $this->option('capability'),
            'code' => $this->option('code'),
            'rar' => $this->option('rar') ? '1' : null,
            'itp' => $this->option('itp') ? '1' : null,
            'has_email' => $this->option('has-email') ? '1' : null,
            'has_phone' => $this->option('has-phone') ? '1' : null,
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''));

        if ($this->option('include-inactive')) {
            $filters->active = null;
        }

        $path = $this->option('output') ?: Storage::disk('local')->path('workshops/exports/workshops-'.now()->format('Y-m-d-His').'.'.$format);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $rows = $exporter->export($filters, $format, $path, (bool) $this->option('public'));
        $this->info("{$rows} workshops written to {$path}");

        return self::SUCCESS;
    }
}
