<?php

namespace App\Catalog\Canonicalization;

final readonly class CanonicalizationResult
{
    public function __construct(
        public string $status,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?float $confidence = null,
        public ?string $message = null,
    ) {}

    public static function published(string $entityType, int $entityId, float $confidence = 100): self
    {
        return new self('published', $entityType, $entityId, $confidence);
    }

    public static function skipped(string $message): self
    {
        return new self('skipped', message: $message);
    }

    public static function ambiguous(string $message, ?float $confidence = null): self
    {
        return new self('ambiguous', confidence: $confidence, message: $message);
    }
}
