<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ArticleBlock::class)->orderBy('position')->orderBy('id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(ArticleVehicle::class);
    }
}
