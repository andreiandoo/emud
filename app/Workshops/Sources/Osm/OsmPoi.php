<?php

namespace App\Workshops\Sources\Osm;

use App\Workshops\Data\LocationData;
use App\Workshops\Data\PhoneNumber;

/**
 * One workshop as someone mapped it on OpenStreetMap.
 */
readonly class OsmPoi
{
    public function __construct(
        public string $osmType,
        public int $osmId,
        public ?string $name,
        public ?float $latitude,
        public ?float $longitude,
        public bool $isArea,
        /** @var array<string, string> every tag, as mapped */
        public array $tags,
        public LocationData $location,
        /** @var list<PhoneNumber> */
        public array $phones = [],
        /** @var list<string> */
        public array $emails = [],
        /** @var list<string> */
        public array $websites = [],
        public ?string $facebook = null,
        public ?string $instagram = null,
        public ?string $openingHours = null,
        public ?string $operator = null,
        public ?string $brand = null,
        /** @var array<string, int> service key => confidence */
        public array $services = [],
    ) {}

    public function reference(): string
    {
        return "{$this->osmType}/{$this->osmId}";
    }

    public function url(): string
    {
        return "https://www.openstreetmap.org/{$this->osmType}/{$this->osmId}";
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
