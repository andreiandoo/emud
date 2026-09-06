<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogPart extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function numbers(): HasMany { return $this->hasMany(CatalogPartNumber::class); }
    public function attributes(): HasMany { return $this->hasMany(CatalogPartAttribute::class); }
    public function fitments(): HasMany { return $this->hasMany(CatalogFitment::class); }
    public function outgoingRelations(): HasMany { return $this->hasMany(CatalogPartRelation::class, 'source_part_id'); }
    public function incomingRelations(): HasMany { return $this->hasMany(CatalogPartRelation::class, 'target_part_id'); }
}
