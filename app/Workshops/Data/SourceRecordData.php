<?php

namespace App\Workshops\Data;

/**
 * One record exactly as a source returned it, before anything is interpreted.
 */
readonly class SourceRecordData
{
    public function __construct(
        public string $recordType,
        public string $externalId,
        public ?array $payload = null,
        public ?string $rawContent = null,
        public ?string $identityKey = null,
        public ?string $countyCode = null,
        public ?string $sourceReference = null,
        public ?int $httpStatus = null,
    ) {}

    /**
     * SHA-256 of the content in a canonical form. Object keys are sorted so that a source
     * reordering its JSON is not mistaken for a change; list order is kept, because in a list the
     * order can be the meaning.
     */
    public function contentHash(): string
    {
        $payload = $this->payload === null
            ? ''
            : json_encode(self::canonical($this->payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        return hash('sha256', ($this->rawContent ?? '')."\n".$payload);
    }

    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
