<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use Auditable, HasFactory;

    /**
     * DISC-05: the only pages Admin › Pages may edit (never create or
     * delete) — the legal/info pages with their own fixed storefront URLs.
     */
    public const ADMIN_EDITABLE_SLUGS = ['privacy-policy', 'terms', 'faq', 'return-policy', 'refund-policy', 'shipping-policy'];

    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /** `content` is the full page body — too large to keep duplicating into every log row. */
    protected function auditExcept(): array
    {
        return ['content', 'created_at', 'updated_at'];
    }
}
