<?php

namespace App\Models;

use App\Enums\CatalogRightsClass;
use App\Enums\SupplierCapability;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierProtocol;
use App\Enums\SupplierStrategicRole;
use App\Enums\SupplierType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'protocol' => SupplierProtocol::class,
            'credentials' => 'encrypted:array',
            'field_mapping' => 'array',
            'settings' => 'array',
            'data_rights_class' => CatalogRightsClass::class,
            'allow_internal_data' => 'boolean',
            'allow_ecommerce_data' => 'boolean',
            'allow_derived_data' => 'boolean',
            'allow_api_redistribution' => 'boolean',
            'attribution_required' => 'boolean',
            'is_active' => 'boolean',
            'last_successful_sync_at' => 'datetime',
            'supplier_type' => SupplierType::class,
            'onboarding_status' => SupplierOnboardingStatus::class,
            'strategic_role' => SupplierStrategicRole::class,
            'contact' => 'array',
            'allowed_countries' => 'array',
            'excluded_countries' => 'array',
            'commercial_profile' => 'array',
            'supports_catalog' => 'boolean',
            'supports_prices' => 'boolean',
            'supports_stock' => 'boolean',
            'supports_realtime_stock' => 'boolean',
            'supports_order_api' => 'boolean',
            'supports_tracking_api' => 'boolean',
            'supports_returns_api' => 'boolean',
            'supports_tecdoc' => 'boolean',
            'supports_aces_pies' => 'boolean',
            'blind_shipping' => 'boolean',
            'neutral_packaging' => 'boolean',
            'merchant_as_sender' => 'boolean',
            'supplier_invoice_in_parcel' => 'boolean',
            'dropship_fee' => 'decimal:2',
            'packaging_fee' => 'decimal:2',
            'minimum_order_value' => 'decimal:2',
            'free_shipping_threshold' => 'decimal:2',
            'restocking_fee_percent' => 'decimal:2',
            'contacted_at' => 'datetime',
            'next_action_due_at' => 'datetime',
        ];
    }

    /**
     * A capability only counts when it has been positively established. Null
     * (never asked) and false (supplier said no) are both unusable.
     */
    public function can(SupplierCapability $capability): bool
    {
        return $this->{$capability->value} === true;
    }

    /** @return array<string, bool|null> */
    public function capabilities(): array
    {
        $capabilities = [];

        foreach (SupplierCapability::cases() as $capability) {
            $capabilities[$capability->value] = $this->{$capability->value};
        }

        return $capabilities;
    }

    /**
     * Whether we may ship this supplier's goods to a destination. An empty
     * allow-list means "not restricted", an explicit list means "only these".
     */
    public function shipsTo(string $countryCode): bool
    {
        $countryCode = strtoupper($countryCode);

        if (in_array($countryCode, array_map('strtoupper', $this->excluded_countries ?? []), true)) {
            return false;
        }

        $allowed = array_map('strtoupper', $this->allowed_countries ?? []);

        return $allowed === [] || in_array($countryCode, $allowed, true);
    }

    /**
     * Dropshipping is not the same as blind dropshipping. Until a supplier has
     * confirmed in writing that the parcel carries no supplier invoice, we treat
     * white-label fulfilment as unavailable.
     */
    public function hasConfirmedBlindFulfilment(): bool
    {
        return $this->blind_shipping === true && $this->supplier_invoice_in_parcel === false;
    }

    /** Suppliers still in the commercial pipeline rather than in production. */
    public function scopeProspects(Builder $query): Builder
    {
        return $query->whereIn('onboarding_status', [
            SupplierOnboardingStatus::NotStarted->value,
            SupplierOnboardingStatus::Contacted->value,
            SupplierOnboardingStatus::ApplicationSent->value,
            SupplierOnboardingStatus::DocsRequested->value,
            SupplierOnboardingStatus::SampleReceived->value,
            SupplierOnboardingStatus::OnHold->value,
        ]);
    }

    public function scopeIntegrationReady(Builder $query): Builder
    {
        return $query->whereIn('onboarding_status', [
            SupplierOnboardingStatus::SampleReceived->value,
            SupplierOnboardingStatus::Approved->value,
            SupplierOnboardingStatus::Contracted->value,
            SupplierOnboardingStatus::Live->value,
        ]);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SupplierSyncRun::class);
    }

    public function syncSchedules(): HasMany
    {
        return $this->hasMany(SupplierSyncSchedule::class);
    }

    public function feedArtifacts(): HasMany
    {
        return $this->hasMany(SupplierFeedArtifact::class);
    }

    public function technicalCatalogSource(): HasOne
    {
        return $this->hasOne(CatalogSource::class);
    }
}
