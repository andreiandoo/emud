<?php

namespace App\Suppliers\Data;

readonly class SupplierRecord
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $sku = null,
        public ?string $ean = null,
        public ?string $manufacturerPartNumber = null,
        public ?string $description = null,
        public ?string $brand = null,
        public ?string $categoryExternalId = null,
        public ?float $costPrice = null,
        public ?float $recommendedRetailPrice = null,
        public string $currency = 'RON',
        public ?int $stockQuantity = null,
        public string $stockStatus = 'unknown',
        public ?int $leadTimeDays = null,
        public ?string $sourceUrl = null,
        public array $images = [],
        public array $attributes = [],
        public array $fitments = [],
        public array $oeNumbers = [],
        public array $iamNumbers = [],
        public array $crossReferences = [],
        public array $supersessions = [],
        public array $raw = [],
        // Commercial and logistics detail. Everything below is optional because feeds
        // vary enormously, and a value we were not given must stay null rather than
        // become a zero that quietly flatters a margin.
        public ?float $costGross = null,
        public ?float $mapPrice = null,
        public ?float $msrp = null,
        public ?float $dropshipFee = null,
        public ?float $handlingFee = null,
        public ?float $shippingCostEstimate = null,
        public ?int $packQuantity = null,
        public ?int $minimumOrderQuantity = null,
        public ?int $dispatchDaysMin = null,
        public ?int $dispatchDaysMax = null,
        public ?string $shippingClass = null,
        public ?float $weightKg = null,
        public ?float $packedWeightKg = null,
        public ?float $lengthCm = null,
        public ?float $widthCm = null,
        public ?float $heightCm = null,
        public ?bool $oversize = null,
        public ?bool $hazmat = null,
        public ?bool $dropshipEligible = null,
        public ?string $warehouseCode = null,
        public ?string $sellerRef = null,
        public ?string $sellerName = null,
        public ?string $sourceUpdatedAt = null,
        // Identity beyond EAN and MPN. A UPC is kept apart from the EAN only because
        // some feeds carry both columns; the matcher treats them as one number system.
        public ?string $tecdocArticleId = null,
        public ?string $upc = null,
    ) {}
}
