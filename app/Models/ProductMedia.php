<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesSearchSuggestions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    use FlushesSearchSuggestions, HasFactory;

    /** Any change to a product image affects search-bar suggestions (see FlushesSearchSuggestions). */
    public const SEARCH_SUGGESTION_FIELDS = null;

    protected $table = 'product_media';

    protected $fillable = [
        'product_id',
        'variant_id',
        'attribute_value_id',
        'path',
        'type',
        'alt',
        'sort_order',
        'is_primary',
        'is_hover',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_hover' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Set on an image chosen for an attribute value (a colour, or a value of
     * any attribute that allows images): it belongs to every variant with it.
     */
    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id');
    }
}
