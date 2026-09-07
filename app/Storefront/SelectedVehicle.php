<?php

namespace App\Storefront;

use App\Models\CustomerVehicle;

/**
 * The vehicle the storefront is currently answering for.
 *
 * Names are carried alongside the ids so that rendering the selection in the header does not
 * cost a query on every page. The ids remain the only thing queries are built from, so a name
 * that has drifted since selection can mislabel the chip but never filter the wrong parts.
 */
final readonly class SelectedVehicle
{
    public function __construct(
        public int $makeId,
        public string $makeName,
        public int $modelId,
        public string $modelName,
        public ?int $generationId = null,
        public ?string $generationName = null,
        public ?int $configurationId = null,
        /** Year of manufacture, when known; fitment year ranges cannot be evaluated without it. */
        public ?int $year = null,
        public ?int $customerVehicleId = null,
    ) {}

    public static function fromCustomerVehicle(CustomerVehicle $vehicle): self
    {
        return new self(
            makeId: (int) $vehicle->make_id,
            makeName: (string) $vehicle->make?->name,
            modelId: (int) $vehicle->model_id,
            modelName: (string) $vehicle->model?->name,
            generationId: $vehicle->generation_id ? (int) $vehicle->generation_id : null,
            generationName: $vehicle->generation?->name,
            configurationId: $vehicle->configuration_id ? (int) $vehicle->configuration_id : null,
            year: $vehicle->year ? (int) $vehicle->year : null,
            customerVehicleId: (int) $vehicle->id,
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        if (! isset($data['make_id'], $data['model_id'])) {
            return null;
        }

        return new self(
            makeId: (int) $data['make_id'],
            makeName: (string) ($data['make_name'] ?? ''),
            modelId: (int) $data['model_id'],
            modelName: (string) ($data['model_name'] ?? ''),
            generationId: isset($data['generation_id']) ? (int) $data['generation_id'] : null,
            generationName: $data['generation_name'] ?? null,
            configurationId: isset($data['configuration_id']) ? (int) $data['configuration_id'] : null,
            year: isset($data['year']) ? (int) $data['year'] : null,
            customerVehicleId: isset($data['customer_vehicle_id']) ? (int) $data['customer_vehicle_id'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'make_id' => $this->makeId,
            'make_name' => $this->makeName,
            'model_id' => $this->modelId,
            'model_name' => $this->modelName,
            'generation_id' => $this->generationId,
            'generation_name' => $this->generationName,
            'configuration_id' => $this->configurationId,
            'year' => $this->year,
            'customer_vehicle_id' => $this->customerVehicleId,
        ];
    }

    public function label(): string
    {
        return trim(implode(' ', array_filter([$this->makeName, $this->modelName, $this->generationName])));
    }

    public function isFromGarage(): bool
    {
        return $this->customerVehicleId !== null;
    }
}
