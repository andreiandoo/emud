<?php

namespace App\Workshops\Matching;

use App\Enums\WorkshopRecordMatchStatus;

/**
 * The matcher's answer for one record, with the candidates it weighed. Stored whatever the
 * outcome, so a reviewer can see why a record was or was not linked.
 */
readonly class MatchDecision
{
    public function __construct(
        public WorkshopRecordMatchStatus $status,
        public ?int $targetId = null,
        public ?string $method = null,
        public ?int $score = null,
        /** @var list<array<string, mixed>> */
        public array $candidates = [],
        public array $evidence = [],
    ) {}

    public function isLinked(): bool
    {
        return $this->status === WorkshopRecordMatchStatus::Matched && $this->targetId !== null;
    }
}
