<?php

namespace App\Commerce;

use Illuminate\Support\Collection;

/**
 * The outcome of routing one line: every offer that could fulfil it, best first, and the
 * reason each of the others could not.
 */
final class RoutingResult
{
    /**
     * @param  Collection<int, array<string, mixed>>  $eligible
     * @param  list<array{supplier: string, reason: string}>  $excluded
     */
    public function __construct(
        public readonly Collection $eligible,
        public readonly array $excluded,
        public readonly bool $soldThroughSuppliers,
    ) {}

    /** @return array<string, mixed>|null */
    public function chosen(): ?array
    {
        return $this->eligible->first();
    }

    /**
     * Supplier-fulfilled, yet nobody can fulfil it right now. A product with no supplier at
     * all is own stock and is not this class's concern.
     */
    public function unavailable(): bool
    {
        return $this->soldThroughSuppliers && $this->eligible->isEmpty();
    }
}
