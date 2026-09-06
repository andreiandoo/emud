<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CatalogApiKey extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(CatalogApiConsumer::class, 'catalog_api_consumer_id');
    }

    /** @return array{model:self, token:string} */
    public static function issue(CatalogApiConsumer $consumer, string $name = 'Default', array $scopes = ['catalog:read']): array
    {
        $token = 'emud_'.Str::random(48);
        $model = self::query()->create([
            'catalog_api_consumer_id' => $consumer->id,
            'name' => $name,
            'key_prefix' => substr($token, 0, 12),
            'key_hash' => hash('sha256', $token),
            'scopes' => $scopes,
        ]);

        return ['model' => $model, 'token' => $token];
    }
}
