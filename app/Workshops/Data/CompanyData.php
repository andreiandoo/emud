<?php

namespace App\Workshops\Data;

/**
 * A legal entity as one source describes it.
 */
readonly class CompanyData
{
    public function __construct(
        public string $legalName,
        public ?string $cui = null,
        public ?string $registrationNumber = null,
        public ?bool $isVatPayer = null,
        public ?LocationData $registeredOffice = null,
        /** @var list<PhoneNumber> */
        public array $phones = [],
        /** @var list<string> */
        public array $emails = [],
    ) {}
}
