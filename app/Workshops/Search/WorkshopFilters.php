<?php

namespace App\Workshops\Search;

/**
 * Everything the registry can be filtered on, shared by the admin list, the internal API and the
 * export so all three answer the same question the same way.
 */
class WorkshopFilters
{
    public const CAPABILITIES = ['4x4', 'awd_permanent', 'offroad', 'ev', 'hybrid', 'trucks', 'itp_4x4'];

    public function __construct(
        public ?string $text = null,
        public ?string $county = null,
        public ?string $locality = null,
        public ?bool $rar = null,
        public ?string $service = null,
        /** rar_authorization | website | osm | manual | inferred, or null for any */
        public ?string $evidence = null,
        public ?string $authorizationCode = null,
        /** @var list<string> */
        public array $capabilities = [],
        public ?bool $itp = null,
        public ?bool $gplGnc = null,
        public ?bool $tlv = null,
        public ?bool $modifications = null,
        public ?bool $hasPhone = null,
        public ?bool $hasEmail = null,
        public ?bool $hasWebsite = null,
        public ?bool $hasCoordinates = null,
        public ?string $source = null,
        public ?int $minConfidence = null,
        public ?bool $active = true,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?float $radiusKm = null,
        /** name | confidence | offroad | distance | recent */
        public string $sort = 'name',
    ) {}

    /** From request or command input, with Romanian and English spellings of yes/no. */
    public static function fromArray(array $input): self
    {
        $bool = static function (mixed $value): ?bool {
            if ($value === null || $value === '') {
                return null;
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (in_array(strtolower((string) $value), ['da', 'y'], true) ? true : (in_array(strtolower((string) $value), ['nu', 'n'], true) ? false : null));
        };
        $capabilities = array_values(array_intersect(
            array_map('strtolower', array_filter(is_array($input['capability'] ?? null) ? $input['capability'] : explode(',', (string) ($input['capability'] ?? '')))),
            self::CAPABILITIES,
        ));

        return new self(
            text: filled($input['q'] ?? null) ? trim((string) $input['q']) : null,
            county: filled($input['county'] ?? null) ? (string) $input['county'] : null,
            locality: filled($input['city'] ?? $input['locality'] ?? null) ? (string) ($input['city'] ?? $input['locality']) : null,
            rar: $bool($input['rar'] ?? null),
            service: filled($input['service'] ?? null) ? (string) $input['service'] : null,
            evidence: filled($input['evidence'] ?? null) ? (string) $input['evidence'] : null,
            authorizationCode: filled($input['code'] ?? null) ? (string) $input['code'] : null,
            capabilities: $capabilities,
            itp: $bool($input['itp'] ?? null),
            gplGnc: $bool($input['gpl'] ?? null),
            tlv: $bool($input['tlv'] ?? null),
            modifications: $bool($input['b4'] ?? null),
            hasPhone: $bool($input['has_phone'] ?? null),
            hasEmail: $bool($input['has_email'] ?? null),
            hasWebsite: $bool($input['has_website'] ?? null),
            hasCoordinates: $bool($input['has_coordinates'] ?? null),
            source: filled($input['source'] ?? null) ? (string) $input['source'] : null,
            minConfidence: is_numeric($input['min_confidence'] ?? null) ? (int) $input['min_confidence'] : null,
            active: array_key_exists('active', $input) ? $bool($input['active']) : true,
            latitude: is_numeric($input['lat'] ?? null) ? (float) $input['lat'] : null,
            longitude: is_numeric($input['lng'] ?? null) ? (float) $input['lng'] : null,
            radiusKm: is_numeric($input['radius'] ?? null) ? max(0.1, min(500, (float) $input['radius'])) : null,
            sort: in_array($input['sort'] ?? null, ['name', 'confidence', 'offroad', 'distance', 'recent'], true) ? $input['sort'] : 'name',
        );
    }
}
