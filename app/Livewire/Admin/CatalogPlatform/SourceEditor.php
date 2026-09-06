<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Enums\CatalogRightsClass;
use App\Models\CatalogSource;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

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

    public function mount(?CatalogSource $source = null): void
    {
        if (! $source?->exists) {
            return;
        }
        $this->source = $source;
        $this->name = $source->name;
        $this->code = $source->code;
        $this->sourceType = $source->source_type;
        $this->protocol = $source->protocol;
        $this->connectorClass = $source->connector_class;
        $this->canonicalizerClass = $source->canonicalizer_class;
        $this->baseUrl = $source->base_url;
        $this->catalogEndpoint = $source->catalog_endpoint;
        $this->rightsClass = $source->rights_class->value;
        $this->allowInternal = $source->allow_internal;
        $this->allowEcommerce = $source->allow_ecommerce;
        $this->allowDerived = $source->allow_derived;
        $this->allowApiRedistribution = $source->allow_api_redistribution;
        $this->allowBulkExport = $source->allow_bulk_export;
        $this->allowMediaRedistribution = $source->allow_media_redistribution;
        $this->attributionRequired = $source->attribution_required;
        $this->isActive = $source->is_active;
        $this->licenseName = $source->license_name;
        $this->licenseUrl = $source->license_url;
        $this->legalNotes = $source->legal_notes;
        $this->credentialsJson = json_encode($source->credentials ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->settingsJson = json_encode($source->settings ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->mappingJson = json_encode($source->field_mapping ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->capabilitiesJson = json_encode($source->capabilities ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function save(): void
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

        session()->flash('status', 'Catalog source saved.');
        $this->redirectRoute('admin.catalog-platform.sources.edit', $this->source, navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.catalog-platform.source-editor', ['rightsClasses' => CatalogRightsClass::cases()]);
    }
}
