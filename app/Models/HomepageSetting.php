<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSetting extends Model
{
    protected $fillable = [
        'section_key',
        'section_name',
        'title',
        'description',
        'eyebrow',
        'action_label',
        'action_href',
        'is_visible',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'json',
    ];
}
