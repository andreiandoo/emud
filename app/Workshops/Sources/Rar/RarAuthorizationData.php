<?php

namespace App\Workshops\Sources\Rar;

use App\Workshops\Data\CompanyData;
use App\Workshops\Data\LocationData;
use App\Workshops\Data\PhoneNumber;
use Carbon\CarbonImmutable;

/**
 * One RAR authorisation document, read.
 */
readonly class RarAuthorizationData
{
    public function __construct(
        public string $system,
        public ?string $status,
        public ?string $number,
        public ?string $exitNumber,
        public ?string $auditFileNumber,
        public ?string $stationCode,
        public ?string $authorizationClass,
        public ?CarbonImmutable $validFrom,
        public ?CarbonImmutable $validUntil,
        public ?CarbonImmutable $initiallyAuthorizedAt,
        public ?CarbonImmutable $revisionDate,
        public ?int $revisionNumber,
        public ?int $workstations,
        public ?int $employees,
        public bool $isMobile,
        public CompanyData $company,
        public LocationData $location,
        /** @var list<PhoneNumber> */
        public array $phones,
        /** @var list<ActivityData> */
        public array $activities,
        /** @var list<string> */
        public array $brands,
        /** Everything in the document except the organisation and the activity lists. */
        public array $summary,
    ) {}
}
