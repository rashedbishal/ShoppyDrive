<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeValue extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'attribute_id',
        'value',
        'color_hex',
        'sort_order',
    ];

    protected function auditLabel(): string
    {
        return "{$this->attribute?->name}: {$this->value}";
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_values');
    }

    public function colorVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'color_value_id');
    }

    public function sizeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'size_value_id');
    }
}
