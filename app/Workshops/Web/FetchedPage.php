<?php

namespace App\Workshops\Web;

use App\Models\WorkshopSourceRecord;

readonly class FetchedPage
{
    public function __construct(
        public string $url,
        public bool $ok,
        public ?int $status = null,
        public string $html = '',
        public ?WorkshopSourceRecord $record = null,
        public ?string $error = null,
    ) {}

    public static function failed(string $url, string $error, ?int $status = null): self
    {
        return new self($url, false, $status, error: $error);
    }
}
