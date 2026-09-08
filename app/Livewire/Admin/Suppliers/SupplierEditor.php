<?php

namespace App\Livewire\Admin\Suppliers;

use App\Enums\CatalogRightsClass;
use App\Enums\SupplierCapability;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierProtocol;
use App\Enums\SupplierStrategicRole;
use App\Enums\SupplierType;
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

    public string $supplierType = 'distributor';

    public string $onboardingStatus = 'not_started';

    public ?string $strategicRole = null;

    public ?string $countryCode = null;

    public ?string $website = null;

    public ?int $qualificationScore = null;

    public ?int $readinessScore = null;

    public ?int $offroadFitScore = null;

    public ?string $onboardingNotes = null;

    public ?string $nextAction = null;

    /**
     * Capabilities are tri-state. '' means "not established yet" and is stored as
     * null, which is deliberately different from an explicit "no".
     *
     * @var array<string, string>
     */
    public array $capabilities = [];

    public ?string $dropshipFee = null;

    public ?string $packagingFee = null;

    public ?string $minimumOrderValue = null;

    public ?string $freeShippingThreshold = null;

    public ?int $paymentTermsDays = null;

    public ?string $termsCurrency = null;

    /** @var array<string, string> Tri-state, same convention as capabilities. */
    public array $fulfilment = [
        'blind_shipping' => '',
        'neutral_packaging' => '',
        'merchant_as_sender' => '',
        'supplier_invoice_in_parcel' => '',
    ];

    public ?int $returnWindowDays = null;

    public ?string $restockingFeePercent = null;

    public ?string $returnFreightPayer = null;

    public ?string $mapPolicy = null;

    public string $allowedCountries = '';

    public string $excludedCountries = '';

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

    public bool $technicalPromotionEnabled = false;

    public bool $technicalPromotionCreateParts = false;

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
        foreach (SupplierCapability::cases() as $capability) {
            $this->capabilities[$capability->value] = '';
        }

        if (! $supplier?->exists) {
            return;
        }

        $this->supplier = $supplier;
        $this->supplierType = $supplier->supplier_type?->value ?? 'distributor';
        $this->onboardingStatus = $supplier->onboarding_status?->value ?? 'not_started';
        $this->strategicRole = $supplier->strategic_role?->value;
        $this->countryCode = $supplier->country_code;
        $this->website = $supplier->website;
        $this->qualificationScore = $supplier->qualification_score;
        $this->readinessScore = $supplier->readiness_score;
        $this->offroadFitScore = $supplier->offroad_fit_score;
        $this->onboardingNotes = $supplier->onboarding_notes;
        $this->nextAction = $supplier->next_action;

        foreach (SupplierCapability::cases() as $capability) {
            $this->capabilities[$capability->value] = $this->toTriState($supplier->{$capability->value});
        }

        foreach (array_keys($this->fulfilment) as $field) {
            $this->fulfilment[$field] = $this->toTriState($supplier->{$field});
        }

        $this->dropshipFee = $supplier->dropship_fee;
        $this->packagingFee = $supplier->packaging_fee;
        $this->minimumOrderValue = $supplier->minimum_order_value;
        $this->freeShippingThreshold = $supplier->free_shipping_threshold;
        $this->paymentTermsDays = $supplier->payment_terms_days;
        $this->termsCurrency = $supplier->terms_currency;
        $this->returnWindowDays = $supplier->return_window_days;
        $this->restockingFeePercent = $supplier->restocking_fee_percent;
        $this->returnFreightPayer = $supplier->return_freight_payer;
        $this->mapPolicy = $supplier->map_policy;
        $this->allowedCountries = implode(', ', $supplier->allowed_countries ?? []);
        $this->excludedCountries = implode(', ', $supplier->excluded_countries ?? []);
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
        $this->technicalPromotionEnabled = (bool) ($supplier->settings['technical_promotion_enabled'] ?? false);
        $this->technicalPromotionCreateParts = (bool) ($supplier->settings['technical_promotion_create_parts'] ?? false);
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
        // Livewire does not run the HTTP middleware that turns empty strings into
        // null, so an untouched optional select would reach rules like url, size
        // or numeric as '' and fail with a confusing message.
        foreach ([
            'strategicRole', 'countryCode', 'website', 'termsCurrency', 'returnFreightPayer', 'mapPolicy',
            'onboardingNotes', 'nextAction', 'dropshipFee', 'packagingFee', 'minimumOrderValue',
            'freeShippingThreshold', 'restockingFeePercent',
        ] as $property) {
            if ($this->{$property} === '') {
                $this->{$property} = null;
            }
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique('suppliers', 'code')->ignore($this->supplier?->id)],
            'protocol' => ['required', Rule::enum(SupplierProtocol::class)],
            'supplierType' => ['required', Rule::enum(SupplierType::class)],
            'onboardingStatus' => ['required', Rule::enum(SupplierOnboardingStatus::class)],
            'strategicRole' => ['nullable', Rule::enum(SupplierStrategicRole::class)],
            'countryCode' => ['nullable', 'string', 'size:2'],
            'website' => ['nullable', 'url', 'max:2048'],
            'qualificationScore' => ['nullable', 'integer', 'min:0', 'max:100'],
            'readinessScore' => ['nullable', 'integer', 'min:1', 'max:5'],
            'offroadFitScore' => ['nullable', 'integer', 'min:0', 'max:100'],
            'capabilities.*' => [Rule::in(['', 'yes', 'no'])],
            'fulfilment.*' => [Rule::in(['', 'yes', 'no'])],
            'dropshipFee' => ['nullable', 'numeric', 'min:0'],
            'packagingFee' => ['nullable', 'numeric', 'min:0'],
            'minimumOrderValue' => ['nullable', 'numeric', 'min:0'],
            'freeShippingThreshold' => ['nullable', 'numeric', 'min:0'],
            'paymentTermsDays' => ['nullable', 'integer', 'min:0', 'max:365'],
            'termsCurrency' => ['nullable', 'string', 'size:3'],
            'returnWindowDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'restockingFeePercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'returnFreightPayer' => ['nullable', 'in:reseller,supplier,case_by_case,unknown'],
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

        $settings = json_decode($this->settingsJson, true, flags: JSON_THROW_ON_ERROR);
        $settings['technical_promotion_enabled'] = $this->technicalPromotionEnabled;
        $settings['technical_promotion_create_parts'] = $this->technicalPromotionCreateParts;

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
            'settings' => $settings,
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
            'supplier_type' => $this->supplierType,
            'onboarding_status' => $this->onboardingStatus,
            'strategic_role' => blank($this->strategicRole) ? null : $this->strategicRole,
            'country_code' => blank($this->countryCode) ? null : strtoupper((string) $this->countryCode),
            'website' => blank($this->website) ? null : trim((string) $this->website),
            'qualification_score' => $this->qualificationScore,
            'readiness_score' => $this->readinessScore,
            'offroad_fit_score' => $this->offroadFitScore,
            'onboarding_notes' => blank($this->onboardingNotes) ? null : $this->onboardingNotes,
            'next_action' => blank($this->nextAction) ? null : $this->nextAction,
            'dropship_fee' => $this->nullableNumber($this->dropshipFee),
            'packaging_fee' => $this->nullableNumber($this->packagingFee),
            'minimum_order_value' => $this->nullableNumber($this->minimumOrderValue),
            'free_shipping_threshold' => $this->nullableNumber($this->freeShippingThreshold),
            'payment_terms_days' => $this->paymentTermsDays,
            'terms_currency' => blank($this->termsCurrency) ? null : strtoupper((string) $this->termsCurrency),
            'return_window_days' => $this->returnWindowDays,
            'restocking_fee_percent' => $this->nullableNumber($this->restockingFeePercent),
            'return_freight_payer' => blank($this->returnFreightPayer) ? null : $this->returnFreightPayer,
            'map_policy' => blank($this->mapPolicy) ? null : $this->mapPolicy,
            'allowed_countries' => $this->countryList($this->allowedCountries),
            'excluded_countries' => $this->countryList($this->excludedCountries),
        ];

        foreach ($this->capabilities as $capability => $value) {
            $payload[$capability] = $this->fromTriState($value);
        }

        foreach ($this->fulfilment as $field => $value) {
            $payload[$field] = $this->fromTriState($value);
        }

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

    private function toTriState(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => '',
        };
    }

    private function fromTriState(string $value): ?bool
    {
        return match ($value) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    private function nullableNumber(?string $value): ?float
    {
        return blank($value) ? null : (float) $value;
    }

    /** @return list<string>|null */
    private function countryList(string $value): ?array
    {
        $codes = collect(preg_split('/[\s,;]+/', $value) ?: [])
            ->map(static fn (string $code): string => strtoupper(trim($code)))
            ->filter(static fn (string $code): bool => preg_match('/^[A-Z]{2}$/', $code) === 1)
            ->unique()
            ->values()
            ->all();

        return $codes === [] ? null : $codes;
    }

    public function render()
    {
        return view('livewire.admin.suppliers.supplier-editor', [
            'protocols' => SupplierProtocol::cases(),
            'rightsClasses' => CatalogRightsClass::cases(),
            'supplierTypes' => SupplierType::cases(),
            'onboardingStatuses' => SupplierOnboardingStatus::cases(),
            'strategicRoles' => SupplierStrategicRole::cases(),
            'capabilityOptions' => SupplierCapability::cases(),
        ]);
    }
}
