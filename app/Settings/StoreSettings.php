<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and writes store-wide settings.
 *
 * Every page renders the shop name, logo and contact details, so the whole set is loaded once
 * and cached rather than queried key by key. Writes clear the cache immediately: a setting the
 * owner just changed showing the old value for fifteen minutes would look broken.
 */
class StoreSettings
{
    private const CACHE_KEY = 'store.settings';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : (bool) $value;
    }

    /** @return array<int|string, mixed> */
    public function array(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->loaded ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    /** @param array<string, mixed> $values */
    public function put(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['group' => $group, 'value' => $value]);
        }

        $this->forget();
    }

    public function forget(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}
