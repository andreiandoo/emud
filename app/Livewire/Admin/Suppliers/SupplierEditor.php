<?php

namespace App\Livewire\Admin\Suppliers;

use App\Enums\CatalogRightsClass;
use App\Enums\SupplierProtocol;
use App\Models\Supplier;
use App\Models\SupplierSyncSchedule;
use Cron\CronExpression;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class SupplierEditor extends Component
{
    public ?Supplier $supplier = null;

    public string $name = '';

    public string $code = '';

    public string $protocol = 'sftp';

    public ?string $connectorClass = null;

    public ?string $catalogEndpoint = null;

    public ?string $stockEndpoint = null;

    public ?string $priceEndpoint = null;

    public string $defaultCurrency = 'EUR';

    public string $timezone = 'Europe/Bucharest';

    public int $priority = 100;

    public string $rightsClass = 'unknown_pending_review';

    public bool $allowInternal = true;

    public bool $allowEcommerce = false;

    public bool $allowDerived = false;

    public bool $allowApiRedistribution = false;

    public bool $attributionRequired = false;

    public bool $isActive = false;

    public ?string $licenseName = null;

    public ?string $licenseUrl = null;

    public ?string $legalNotes = null;

    public string $credentialsJson = '{}';

    public string $settingsJson = '{}';

    public string $mappingJson = '{}';

    /** @var array<string, array{cron: string, timezone: string, enabled: bool}> */
    public array $schedules = [
        'catalog' => ['cron' => '10 2 * * *', 'timezone' => 'Europe/Bucharest', 'enabled' => false],
        'prices' => ['cron' => '0 * * * *', 'timezone' => 'Europe/Bucharest', 'enabled' => false],
        'stock' => ['cron' => '*/15 * * * *', 'timezone' => 'Europe/Bucharest', 'enabled' => false],
    ];

    public function mount(?Supplier $supplier = null): void
    {
        if (! $supplier?->exists) {
            return;
        }

        $this->supplier = $supplier;
        $this->name = $supplier->name;
        $this->code = $supplier->code;
        $this->protocol = $supplier->protocol->value;
        $this->connectorClass = $supplier->connector_class;
        $this->catalogEndpoint = $supplier->catalog_endpoint;
        $this->stockEndpoint = $supplier->stock_endpoint;
        $this->priceEndpoint = $supplier->price_endpoint;
        $this->defaultCurrency = $supplier->default_currency;
        $this->timezone = $supplier->timezone;
        $this->priority = $supplier->priority;
        $this->rightsClass = $supplier->data_rights_class->value;
        $this->allowInternal = $supplier->allow_internal_data;
        $this->allowEcommerce = $supplier->allow_ecommerce_data;
        $this->allowDerived = $supplier->allow_derived_data;
        $this->allowApiRedistribution = $supplier->allow_api_redistribution;
        $this->attributionRequired = $supplier->attribution_required;
        $this->isActive = $supplier->is_active;
        $this->licenseName = $supplier->license_name;
        $this->licenseUrl = $supplier->license_url;
        $this->legalNotes = $supplier->legal_notes;
        $this->credentialsJson = json_encode($supplier->credentials ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->settingsJson = json_encode($supplier->settings ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->mappingJson = json_encode($supplier->field_mapping ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

        foreach ($supplier->syncSchedules()->get() as $schedule) {
            $this->schedules[$schedule->mode] = [
                'cron' => $schedule->cron_expression,
                'timezone' => $schedule->timezone,
                'enabled' => $schedule->is_enabled,
            ];
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique('suppliers', 'code')->ignore($this->supplier?->id)],
            'protocol' => ['required', Rule::enum(SupplierProtocol::class)],
            'connectorClass' => ['nullable', 'string', 'max:255'],
            'catalogEndpoint' => ['nullable', 'string', 'max:2048'],
            'stockEndpoint' => ['nullable', 'string', 'max:2048'],
            'priceEndpoint' => ['nullable', 'string', 'max:2048'],
            'defaultCurrency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
            'priority' => ['required', 'integer', 'min:0', 'max:65535'],
            'rightsClass' => ['required', Rule::enum(CatalogRightsClass::class)],
            'licenseUrl' => ['nullable', 'url'],
            'credentialsJson' => ['required', 'json'],
            'settingsJson' => ['required', 'json'],
            'mappingJson' => ['required', 'json'],
            'schedules.catalog.cron' => ['required', 'string', 'max:100'],
            'schedules.catalog.timezone' => ['required', 'timezone'],
            'schedules.prices.cron' => ['required', 'string', 'max:100'],
            'schedules.prices.timezone' => ['required', 'timezone'],
            'schedules.stock.cron' => ['required', 'string', 'max:100'],
            'schedules.stock.timezone' => ['required', 'timezone'],
        ]);

        foreach ($this->schedules as $schedule) {
            if (! CronExpression::isValidExpression($schedule['cron'])) {
                $this->addError('schedules', "Invalid cron expression: {$schedule['cron']}");

                return;
            }
        }

        $payload = [
            'name' => trim($this->name),
            'code' => strtoupper(trim($this->code)),
            'protocol' => $this->protocol,
            'connector_class' => blank($this->connectorClass) ? null : trim((string) $this->connectorClass),
            'catalog_endpoint' => blank($this->catalogEndpoint) ? null : trim((string) $this->catalogEndpoint),
            'stock_endpoint' => blank($this->stockEndpoint) ? null : trim((string) $this->stockEndpoint),
            'price_endpoint' => blank($this->priceEndpoint) ? null : trim((string) $this->priceEndpoint),
            'credentials' => json_decode($this->credentialsJson, true, flags: JSON_THROW_ON_ERROR),
            'field_mapping' => json_decode($this->mappingJson, true, flags: JSON_THROW_ON_ERROR),
            'settings' => json_decode($this->settingsJson, true, flags: JSON_THROW_ON_ERROR),
            'default_currency' => strtoupper($this->defaultCurrency),
            'timezone' => $this->timezone,
            'priority' => $this->priority,
            'data_rights_class' => $this->rightsClass,
            'allow_internal_data' => $this->allowInternal,
            'allow_ecommerce_data' => $this->allowEcommerce,
            'allow_derived_data' => $this->allowDerived,
            'allow_api_redistribution' => $this->allowApiRedistribution,
            'attribution_required' => $this->attributionRequired,
            'is_active' => $this->isActive,
            'license_name' => blank($this->licenseName) ? null : $this->licenseName,
            'license_url' => blank($this->licenseUrl) ? null : $this->licenseUrl,
            'legal_notes' => blank($this->legalNotes) ? null : $this->legalNotes,
        ];

        $this->supplier = $this->supplier?->exists
            ? tap($this->supplier)->update($payload)
            : Supplier::query()->create($payload);

        foreach ($this->schedules as $mode => $schedule) {
            SupplierSyncSchedule::query()->updateOrCreate(
                ['supplier_id' => $this->supplier->id, 'mode' => $mode],
                [
                    'cron_expression' => $schedule['cron'],
                    'timezone' => $schedule['timezone'],
                    'is_enabled' => (bool) $schedule['enabled'],
                ],
            );
        }

        session()->flash('status', 'Supplier feed configuration saved.');
        $this->redirectRoute('admin.suppliers.edit', $this->supplier, navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.suppliers.supplier-editor', [
            'protocols' => SupplierProtocol::cases(),
            'rightsClasses' => CatalogRightsClass::cases(),
        ]);
    }
}
