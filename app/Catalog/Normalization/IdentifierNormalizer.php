<?php

namespace App\Catalog\Normalization;

use Illuminate\Support\Str;

class IdentifierNormalizer
{
    public function normalize(string $value): string
    {
        return Str::upper(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    public function compact(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', $this->normalize($value)) ?? $this->normalize($value);
    }

    public function slug(string $value): string
    {
        return Str::slug($this->normalize($value));
    }
}
