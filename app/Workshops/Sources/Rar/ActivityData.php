<?php

namespace App\Workshops\Sources\Rar;

/**
 * One authorised activity, with the code exactly as RAR sends it and its published wording.
 */
readonly class ActivityData
{
    public function __construct(
        public string $code,
        public string $entryCode,
        public ?string $parentCode,
        public int $depth,
        public ?string $rawDescription,
        /** @var list<string> */
        public array $vehicleCategories = [],
        /** @var list<string> */
        public array $limitations = [],
        /** @var list<array{code: string|null, text: string}> */
        public array $restrictions = [],
        /** @var list<string> */
        public array $observations = [],
        public ?array $raw = null,
        public ?string $displayCode = null,
    ) {}

    /** A1_2_1_1 reads A1.2.1.1; codes that are names rather than numbers stay as they are. */
    public function displayCode(): string
    {
        if ($this->displayCode !== null) {
            return $this->displayCode;
        }

        return preg_match('/^[AB]\d+(?:_\d+)*$/', $this->code) === 1 ? str_replace('_', '.', $this->code) : $this->code;
    }

    /** The wording without the code RAR prints in front of it. */
    public function description(): ?string
    {
        if ($this->rawDescription === null) {
            return null;
        }

        $description = preg_replace('/^[AB]\d+(?:\.\d+)*\.?\s+/u', '', $this->rawDescription) ?? $this->rawDescription;

        return $description === '' ? $this->rawDescription : $description;
    }
}
