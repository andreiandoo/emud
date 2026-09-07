<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Catalog\Sources\CatalogSourceRegistry;
use App\Enums\CatalogRightsClass;
use App\Jobs\SyncCatalogSource;
use App\Models\CatalogSource;
use App\Models\CatalogSourceSchedule;
use Cron\CronExpression;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts::admin')]
class SourceEditor extends Component
{
    public ?CatalogSource $source = null;

    public string $name = '';

    public string $code = '';

    public string $sourceType = 'open_dataset';

    public string $protocol = 'http';

    public ?string $connectorClass = null;

    public ?string $canonicalizerClass = null;

    public ?string $baseUrl = null;

    public ?string $catalogEndpoint = null;

    public string $rightsClass = 'unknown_pending_review';

    public bool $allowInternal = true;

    public bool $allowEcommerce = false;

    public bool $allowDerived = false;

    public bool $allowApiRedistribution = false;

    public bool $allowBulkExport = false;

    public bool $allowMediaRedistribution = false;

    public bool $attributionRequired = false;

    public bool $isActive = true;

    public ?string $licenseName = null;

    public ?string $licenseUrl = null;

    public ?string $legalNotes = null;

    public string $credentialsJson = '{}';

    public string $settingsJson = '{}';

    public string $mappingJson = '{}';

    public string $capabilitiesJson = '{}';

    /** @var array<int, array{mode:string,cron_expression:string,timezone:string,is_enabled:bool,last_dispatched_at:?string}> */
    public array $schedules = [];

    /** @var array<string, mixed>|null */
    public ?array $connectionResult = null;

    public function mount(?CatalogSource $source = null): void
    {
        if (! $source?->exists) {
            $this->addSchedule();

            return;
        }

        $this->source = $source;
        $this->name = (string) $source->name;
        $this->code = (string) $source->code;
        $this->sourceType = (string) ($source->source_type ?? 'open_dataset');
        $this->protocol = (string) ($source->protocol ?? 'http');
        $this->connectorClass = $source->connector_class;
        $this->canonicalizerClass = $source->canonicalizer_class;
        $this->baseUrl = $source->base_url;
        $this->catalogEndpoint = $source->catalog_endpoint;
        $this->rightsClass = $source->rights_class?->value ?? 'unknown_pending_review';
        $this->allowInternal = (bool) ($source->allow_internal ?? true);
        $this->allowEcommerce = (bool) ($source->allow_ecommerce ?? false);
        $this->allowDerived = (bool) ($source->allow_derived ?? false);
        $this->allowApiRedistribution = (bool) ($source->allow_api_redistribution ?? false);
        $this->allowBulkExport = (bool) ($source->allow_bulk_export ?? false);
        $this->allowMediaRedistribution = (bool) ($source->allow_media_redistribution ?? false);
        $this->attributionRequired = (bool) ($source->attribution_required ?? false);
        $this->isActive = (bool) ($source->is_active ?? true);
        $this->licenseName = $source->license_name;
        $this->licenseUrl = $source->license_url;
        $this->legalNotes = $source->legal_notes;
        $this->credentialsJson = json_encode($source->credentials ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->settingsJson = json_encode($source->settings ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->mappingJson = json_encode($source->field_mapping ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->capabilitiesJson = json_encode($source->capabilities ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->schedules = $source->schedules()
            ->orderBy('mode')
            ->get()
            ->map(fn (CatalogSourceSchedule $schedule) => [
                'mode' => $schedule->mode,
                'cron_expression' => $schedule->cron_expression,
                'timezone' => $schedule->timezone,
                'is_enabled' => $schedule->is_enabled,
                'last_dispatched_at' => $schedule->last_dispatched_at?->toIso8601String(),
            ])
            ->all();
    }

    public function addSchedule(): void
    {
        $this->schedules[] = [
            'mode' => $this->schedules === [] ? 'catalog' : '',
            'cron_expression' => $this->schedules === [] ? '0 2 * * *' : '0 3 * * *',
            'timezone' => 'Europe/Bucharest',
            'is_enabled' => true,
            'last_dispatched_at' => null,
        ];
    }

    public function removeSchedule(int $index): void
    {
        if (array_key_exists($index, $this->schedules)) {
            unset($this->schedules[$index]);
            $this->schedules = array_values($this->schedules);
        }
    }

    public function save(): void
    {
        $this->validateSource();
        $this->validateSchedules();

        $payload = [
            'public_id' => $this->source?->public_id ?? (string) Str::ulid(),
            'name' => $this->name,
            'code' => strtoupper(trim($this->code)),
            'source_type' => $this->sourceType,
            'protocol' => $this->protocol,
            'connector_class' => blank($this->connectorClass) ? null : $this->connectorClass,
            'canonicalizer_class' => blank($this->canonicalizerClass) ? null : $this->canonicalizerClass,
            'base_url' => blank($this->baseUrl) ? null : $this->baseUrl,
            'catalog_endpoint' => blank($this->catalogEndpoint) ? null : $this->catalogEndpoint,
            'rights_class' => $this->rightsClass,
            'allow_internal' => $this->allowInternal,
            'allow_ecommerce' => $this->allowEcommerce,
            'allow_derived' => $this->allowDerived,
            'allow_api_redistribution' => $this->allowApiRedistribution,
            'allow_bulk_export' => $this->allowBulkExport,
            'allow_media_redistribution' => $this->allowMediaRedistribution,
            'attribution_required' => $this->attributionRequired,
            'is_active' => $this->isActive,
            'license_name' => $this->licenseName,
            'license_url' => $this->licenseUrl,
            'legal_notes' => $this->legalNotes,
            'credentials' => json_decode($this->credentialsJson, true, flags: JSON_THROW_ON_ERROR),
            'settings' => json_decode($this->settingsJson, true, flags: JSON_THROW_ON_ERROR),
            'field_mapping' => json_decode($this->mappingJson, true, flags: JSON_THROW_ON_ERROR),
            'capabilities' => json_decode($this->capabilitiesJson, true, flags: JSON_THROW_ON_ERROR),
        ];

        $this->source = $this->source?->exists
            ? tap($this->source)->update($payload)
            : CatalogSource::query()->create($payload);

        $this->persistSchedules();
        session()->flash('status', 'Catalog source and schedules saved.');
        $this->redirectRoute('admin.catalog-platform.sources.edit', $this->source, navigate: true);
    }

    public function testConnection(CatalogSourceRegistry $registry): void
    {
        $this->connectionResult = null;
        if (! $this->source?->exists) {
            $this->addError('connection', 'Save the source before testing its connection.');

            return;
        }

        try {
            $source = $this->source->fresh();
            $result = $registry->for($source)->testConnection($source);
            $this->connectionResult = [
                'ok' => (bool) ($result['ok'] ?? false),
                'tested_at' => now()->toIso8601String(),
                'details' => $this->safeConnectionDetails($result),
            ];
        } catch (Throwable $exception) {
            report($exception);
            $this->connectionResult = [
                'ok' => false,
                'tested_at' => now()->toIso8601String(),
                'details' => ['message' => $exception->getMessage()],
            ];
        }
    }

    public function runNow(string $mode = 'catalog'): void
    {
        if (! $this->source?->exists) {
            $this->addError('connection', 'Save the source before running an import.');

            return;
        }

        $mode = trim($mode) ?: 'catalog';
        SyncCatalogSource::dispatch($this->source->id, $mode);
        session()->flash('operationStatus', "Catalog source sync queued for mode: {$mode}.");
    }

    public function runConfiguredModes(): void
    {
        if (! $this->source?->exists) {
            $this->addError('connection', 'Save the source before running imports.');

            return;
        }

        $modes = collect($this->schedules)
            ->filter(fn (array $schedule) => (bool) ($schedule['is_enabled'] ?? false))
            ->pluck('mode')
            ->map(fn ($mode) => trim((string) $mode))
            ->filter()
            ->unique()
            ->values();

        if ($modes->isEmpty()) {
            $modes = collect(['catalog']);
        }

        foreach ($modes as $mode) {
            SyncCatalogSource::dispatch($this->source->id, $mode);
        }

        session()->flash('operationStatus', 'Queued modes: '.$modes->implode(', ').'.');
    }

    public function render()
    {
        return view('livewire.admin.catalog-platform.source-editor', [
            'rightsClasses' => CatalogRightsClass::cases(),
        ]);
    }

    private function validateSource(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique('catalog_sources', 'code')->ignore($this->source?->id)],
            'sourceType' => ['required', 'string', 'max:48'],
            'protocol' => ['required', 'string', 'max:24'],
            'connectorClass' => ['nullable', 'string', 'max:255'],
            'canonicalizerClass' => ['nullable', 'string', 'max:255'],
            'rightsClass' => ['required', Rule::enum(CatalogRightsClass::class)],
            'baseUrl' => ['nullable', 'url'],
            'catalogEndpoint' => ['nullable', 'url'],
            'licenseUrl' => ['nullable', 'url'],
            'credentialsJson' => ['required', 'json'],
            'settingsJson' => ['required', 'json'],
            'mappingJson' => ['required', 'json'],
            'capabilitiesJson' => ['required', 'json'],
        ]);
    }

    private function validateSchedules(): void
    {
        $this->validate([
            'schedules' => ['array'],
            'schedules.*.mode' => ['required', 'string', 'max:32', 'distinct'],
            'schedules.*.cron_expression' => ['required', 'string', 'max:100'],
            'schedules.*.timezone' => ['required', 'timezone'],
            'schedules.*.is_enabled' => ['boolean'],
        ]);

        foreach ($this->schedules as $index => $schedule) {
            if (! CronExpression::isValidExpression((string) $schedule['cron_expression'])) {
                $this->addError("schedules.{$index}.cron_expression", 'Invalid cron expression.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            throw ValidationException::withMessages($this->getErrorBag()->toArray());
        }
    }

    private function persistSchedules(): void
    {
        $modes = [];
        foreach ($this->schedules as $schedule) {
            $mode = trim((string) $schedule['mode']);
            $modes[] = $mode;
            CatalogSourceSchedule::query()->updateOrCreate([
                'catalog_source_id' => $this->source->id,
                'mode' => $mode,
            ], [
                'cron_expression' => trim((string) $schedule['cron_expression']),
                'timezone' => (string) $schedule['timezone'],
                'is_enabled' => (bool) $schedule['is_enabled'],
            ]);
        }

        $query = CatalogSourceSchedule::query()->where('catalog_source_id', $this->source->id);
        $modes === [] ? $query->delete() : $query->whereNotIn('mode', $modes)->delete();
    }

    /** @param array<string, mixed> $result */
    private function safeConnectionDetails(array $result): array
    {
        $blocked = ['credentials', 'password', 'token', 'authorization', 'api_key', 'secret', 'private_key'];

        return collect($result)
            ->reject(fn ($value, $key) => in_array(strtolower((string) $key), $blocked, true))
            ->map(fn ($value) => is_scalar($value) || $value === null ? $value : '[structured result]')
            ->all();
    }
}
