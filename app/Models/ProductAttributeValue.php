<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value_json' => 'array', 'value_boolean' => 'boolean', 'value_number' => 'decimal:6'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class, 'option_id');
    }

    /**
     * The value as a customer should read it: the chosen option's label, a number with its unit,
     * "Da"/"Nu" for a flag, or the free text.
     *
     * Trailing zeros are trimmed off numbers because the column stores six decimals and nobody
     * wants to read "50.000000 mm".
     */
    public function displayValue(): ?string
    {
        if ($this->option !== null) {
            return $this->option->label;
        }

        if ($this->value_boolean !== null) {
            return $this->value_boolean ? 'Da' : 'Nu';
        }

        if ($this->value_number !== null) {
            $number = rtrim(rtrim((string) $this->value_number, '0'), '.');

            return trim($number.' '.(string) $this->attribute?->unit);
        }

        return filled($this->value_text) ? (string) $this->value_text : null;
    }
}
