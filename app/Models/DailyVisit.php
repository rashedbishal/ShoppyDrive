<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyVisit extends Model
{
    protected $fillable = [
        'date',
        'visitor_count',
        'page_view_count',
        'mobile_visitors',
        'tablet_visitors',
        'desktop_visitors',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'visitor_count' => 'integer',
            'page_view_count' => 'integer',
            'mobile_visitors' => 'integer',
            'tablet_visitors' => 'integer',
            'desktop_visitors' => 'integer',
        ];
    }
}
