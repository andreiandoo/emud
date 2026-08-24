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
        public array $raw = [],
    ) {}
}
