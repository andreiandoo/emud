<?php

namespace App\Workshops\Data;

/**
 * An independent unit of work within a source: for RAR, one county as the registry names it.
 * Small enough that one job fetches it, so no single job makes thousands of requests.
 */
readonly class SourcePartition
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $countyCode = null,
        public array $parameters = [],
    ) {}

    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'county_code' => $this->countyCode, 'parameters' => $this->parameters];
    }

    public static function fromArray(array $data): self
    {
        return new self((string) $data['key'], (string) ($data['label'] ?? $data['key']), $data['county_code'] ?? null, (array) ($data['parameters'] ?? []));
    }
}
