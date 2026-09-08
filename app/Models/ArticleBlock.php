<?php

namespace App\Models;

use App\Enums\ArticleBlockType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleBlock extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => ArticleBlockType::class, 'data' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }
}
