<?php

namespace App\Workshops\Sources\Onrc;

/**
 * One quarterly publication of the trade register on data.gov.ro ("Firme înregistrate la
 * Registrul Comerțului până la data de …"), with its files and the nomenclature package that
 * decodes them.
 */
readonly class OnrcRelease
{
    public function __construct(
        public string $key,
        public string $title,
        public ?string $publishedAt,
        /** @var array<string, array{id: string|null, url: string, size: int|null, last_modified: string|null}> file name => resource */
        public array $resources,
    ) {}

    public function resource(string $name): ?array
    {
        return $this->resources[strtoupper($name)] ?? null;
    }

    public function toArray(): array
    {
        return ['key' => $this->key, 'title' => $this->title, 'published_at' => $this->publishedAt, 'resources' => $this->resources];
    }

    public static function fromArray(array $data): self
    {
        return new self((string) $data['key'], (string) ($data['title'] ?? $data['key']), $data['published_at'] ?? null, (array) ($data['resources'] ?? []));
    }
}
