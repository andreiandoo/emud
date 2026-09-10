<?php

namespace App\Workshops\Sources\Onrc;

use App\Workshops\Data\LocationData;

/**
 * One company as the trade register describes it: legal identity, registered office, status and
 * every authorised CAEN activity. Nothing here says where a workshop is.
 */
readonly class OnrcCompany
{
    /** "funcțiune" in ONRC's status nomenclature: the company is operating. */
    public const ACTIVE_STATUS = '1048';

    public function __construct(
        public string $registrationNumber,
        public string $legalName,
        public ?string $cui,
        public ?string $euid,
        public ?string $legalForm,
        public ?string $registeredOn,
        public LocationData $office,
        public ?string $website,
        /** @var list<array{code: string, version: string, label: string|null}> */
        public array $caen,
        /** @var list<array{code: string, label: string|null}> */
        public array $statuses,
        public bool $automotive,
    ) {}

    public function isActive(): bool
    {
        return in_array(self::ACTIVE_STATUS, array_column($this->statuses, 'code'), true);
    }

    public function statusLabel(): ?string
    {
        $labels = array_values(array_filter(array_column($this->statuses, 'label')));

        return $labels === [] ? null : implode(', ', array_unique($labels));
    }

    public function statusCode(): ?string
    {
        $codes = array_column($this->statuses, 'code');

        return in_array(self::ACTIVE_STATUS, $codes, true) ? self::ACTIVE_STATUS : ($codes[0] ?? null);
    }
}
