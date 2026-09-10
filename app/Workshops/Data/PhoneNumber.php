<?php

namespace App\Workshops\Data;

/**
 * One telephone number: the text a source wrote, and its E.164 form for comparison.
 */
readonly class PhoneNumber
{
    public function __construct(
        public string $raw,
        public string $e164,
        /** mobile | landline | special | international */
        public string $type,
    ) {}

    public function isRomanian(): bool
    {
        return str_starts_with($this->e164, '+40');
    }

    /** The domestic form a Romanian writes: 0722123456. */
    public function national(): ?string
    {
        return $this->isRomanian() ? '0'.substr($this->e164, 3) : null;
    }

    /** Grouped the way the number is read aloud: 0722 123 456, 021 242 2744. */
    public function display(): string
    {
        $national = $this->national();

        if ($national === null) {
            return $this->e164;
        }

        return str_starts_with($national, '021') || str_starts_with($national, '031')
            ? substr($national, 0, 3).' '.substr($national, 3, 3).' '.substr($national, 6)
            : substr($national, 0, 4).' '.substr($national, 4, 3).' '.substr($national, 7);
    }

    public function contactType(): string
    {
        return $this->type === 'mobile' ? 'mobile' : 'phone';
    }
}
